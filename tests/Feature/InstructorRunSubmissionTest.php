<?php

namespace Tests\Feature;

use App\Models\Anomaly;
use App\Models\Competency;
use App\Models\Group;
use App\Models\Laboratory;
use App\Models\LabSession;
use App\Models\Module;
use App\Models\SchoolClass;
use App\Models\StudentCompetency;
use App\Models\TelemetryLog;
use App\Models\User;
use App\Services\SandboxExecutionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * An instructor runs a student's handed-in code from the attempt views (results page and the
 * monitoring drawer) and sees what it printed. The run goes to Judge0 only, never changes the
 * attempt, and only the class's instructor (or an admin) may start it.
 */
class InstructorRunSubmissionTest extends TestCase
{
    use RefreshDatabase;

    private const MAIN_JAVA = "import java.util.Scanner;\n\npublic class Main {\n    public static void main(String[] args) {\n        System.out.println(\"Hello from the submission\");\n    }\n}\n";

    protected User $instructor;

    protected User $student;

    protected SchoolClass $schoolClass;

    protected Laboratory $laboratory;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.judge0.url' => 'https://judge0.test',
            'services.judge0.key' => null,
            'services.judge0.rapidapi_host' => null,
        ]);

        $this->instructor = $this->user('Instructor Doe', 'instructor');
        $this->student = $this->user('Student Jane', 'student');

        $this->schoolClass = SchoolClass::create([
            'name' => 'Java Basics',
            'code' => 'JAVA101',
            'instructor_id' => $this->instructor->id,
        ]);
        $this->schoolClass->students()->attach($this->student->id, ['status' => 'enrolled']);

        $module = Module::create(['class_id' => $this->schoolClass->id, 'title' => 'Basics', 'content' => 'Content']);

        $this->laboratory = Laboratory::create([
            'title' => 'Hello World',
            'description' => 'Print a greeting.',
            'time_limit' => 60,
            'module_id' => $module->id,
            'tasks_definition' => [['id' => 1, 'task' => 'Print a greeting']],
        ]);
    }

    private function user(string $name, string $role): User
    {
        return User::create([
            'name' => $name,
            'email' => strtolower(str_replace(' ', '.', $name)).'@example.com',
            'password' => bcrypt('password'),
            'role' => $role,
        ]);
    }

    private function submittedSession(array $attributes = []): LabSession
    {
        $start = now()->subHours(2);

        return LabSession::create(array_merge([
            'lab_id' => $this->laboratory->id,
            'user_id' => $this->student->id,
            'status' => 'completed',
            'started_at' => $start,
            'ended_at' => $start->copy()->addMinutes(30),
            'performance_score' => 88,
            'completed_tasks' => [1],
            'ai_grade_summary' => ['summary' => 'Prints the greeting.', 'student_feedback' => ['tasks' => []]],
            'submitted_code' => self::MAIN_JAVA,
            'submitted_files' => [['name' => 'Main.java', 'content' => self::MAIN_JAVA, 'is_primary' => true]],
        ], $attributes));
    }

    private function fakeJudge0(array $result): void
    {
        Http::fake(['judge0.test/*' => Http::response($result)]);
    }

    private function accepted(string $stdout = "Hello from the submission\n"): array
    {
        return [
            'status' => ['id' => 3, 'description' => 'Accepted'],
            'stdout' => base64_encode($stdout),
            'stderr' => null,
            'exit_code' => 0,
            'time' => '0.042',
            'memory' => 15360,
        ];
    }

    private function runUrl(LabSession $session): string
    {
        return "/instructor/sessions/{$session->id}/run";
    }

    public function test_instructor_runs_the_submitted_code_and_sees_the_output(): void
    {
        $session = $this->submittedSession();
        $this->fakeJudge0($this->accepted());

        $this->actingAs($this->instructor)->postJson($this->runUrl($session))
            ->assertOk()
            ->assertJson([
                'status' => 'success',
                'output' => "Hello from the submission\n",
                'errors' => null,
                'stderr' => '',
                'compile_error' => false,
                'exit_code' => 0,
                'cpu_time_ms' => 42,
                'memory_kb' => 15360,
                'language' => 'java',
                'files' => ['Main.java'],
                'skipped_files' => [],
            ])
            ->assertJsonStructure(['execution_time_ms', 'ran_at']);

        // The code as it was handed in, compiled the way the student's own run is
        Http::assertSentCount(1);
        Http::assertSent(fn (Request $request) => $request['language_id'] === 62
            && str_contains(base64_decode($request['source_code']), 'System.out.println("Hello from the submission");')
            && ! isset($request['stdin']));
    }

    public function test_the_saved_submission_runs_not_the_students_current_work(): void
    {
        // A check-progress saved newer code on another, still open attempt
        $session = $this->submittedSession();
        $this->submittedSession([
            'status' => 'in_progress',
            'ended_at' => null,
            'ai_grade_summary' => null,
            'submitted_code' => 'class Main { public static void main(String[] a) { System.out.println("draft"); } }',
            'submitted_files' => [['name' => 'Main.java', 'content' => 'class Main { public static void main(String[] a) { System.out.println("draft"); } }', 'is_primary' => true]],
        ]);
        $this->fakeJudge0($this->accepted());

        $this->actingAs($this->instructor)->postJson($this->runUrl($session), ['code' => 'class Injected {}'])->assertOk();

        Http::assertSent(function (Request $request) {
            $source = base64_decode($request['source_code']);

            return str_contains($source, 'Hello from the submission') && ! str_contains($source, 'draft') && ! str_contains($source, 'Injected');
        });
    }

    public function test_optional_stdin_is_given_to_the_program(): void
    {
        $session = $this->submittedSession();
        $this->fakeJudge0($this->accepted("Hi, Ana\n"));

        $this->actingAs($this->instructor)->postJson($this->runUrl($session), ['stdin' => "Ana\n"])
            ->assertOk()
            ->assertJsonPath('output', "Hi, Ana\n");

        Http::assertSent(fn (Request $request) => base64_decode($request['stdin']) === "Ana\n");
    }

    public function test_stdin_is_capped(): void
    {
        $session = $this->submittedSession();
        Http::fake();

        $this->actingAs($this->instructor)->postJson($this->runUrl($session), ['stdin' => str_repeat('x', 65537)])
            ->assertStatus(422)
            ->assertJsonValidationErrors('stdin');

        Http::assertNothingSent();
    }

    public function test_compile_errors_are_shown_as_compile_errors(): void
    {
        $session = $this->submittedSession();
        $this->fakeJudge0([
            'status' => ['id' => 6, 'description' => 'Compilation Error'],
            'compile_output' => base64_encode("Main.java:5: error: ';' expected"),
            'exit_code' => null,
        ]);

        $this->actingAs($this->instructor)->postJson($this->runUrl($session))
            ->assertOk()
            ->assertJson(['status' => 'error', 'compile_error' => true, 'exit_code' => null, 'output' => ''])
            ->assertJsonPath('errors', "Java Compilation Error:\nMain.java:5: error: ';' expected");
    }

    public function test_runtime_errors_show_stderr_and_the_exit_code(): void
    {
        $session = $this->submittedSession();
        $this->fakeJudge0([
            'status' => ['id' => 11, 'description' => 'Runtime Error (NZEC)'],
            'stdout' => base64_encode("before the crash\n"),
            'stderr' => base64_encode("Exception in thread \"main\" java.lang.ArithmeticException: / by zero\n"),
            'exit_code' => 1,
            'time' => '0.1',
            'memory' => 2048,
        ]);

        $this->actingAs($this->instructor)->postJson($this->runUrl($session))
            ->assertOk()
            ->assertJson([
                'status' => 'error',
                'compile_error' => false,
                'exit_code' => 1,
                'output' => "before the crash\n",
                'stderr' => "Exception in thread \"main\" java.lang.ArithmeticException: / by zero\n",
            ]);
    }

    public function test_stderr_of_a_successful_run_is_kept(): void
    {
        $session = $this->submittedSession();
        $this->fakeJudge0(['stderr' => base64_encode("warning: low stock\n")] + $this->accepted());

        $this->actingAs($this->instructor)->postJson($this->runUrl($session))
            ->assertOk()
            ->assertJson(['status' => 'success', 'errors' => null, 'stderr' => "warning: low stock\n"]);
    }

    public function test_running_changes_nothing_on_the_attempt(): void
    {
        $session = $this->submittedSession(['instructor_grade_override' => 91, 'instructor_override_reason' => 'Nice error handling']);
        $competency = Competency::create(['code' => 'JAVA-01', 'name' => 'Java basics']);
        StudentCompetency::create(['user_id' => $this->student->id, 'competency_id' => $competency->id, 'score_achieved' => 88]);
        TelemetryLog::create(['lab_session_id' => $session->id, 'event_type' => 'session_completed', 'payload' => ['language' => 'java', 'final_score' => 88]]);

        $before = [
            'session' => DB::table('lab_sessions')->where('id', $session->id)->first(),
            'competencies' => DB::table('student_competencies')->get(),
            'telemetry' => TelemetryLog::count(),
            'anomalies' => Anomaly::count(),
            'notifications' => DB::table('notifications')->count(),
        ];

        // Output that matches the task text, and a run that crashes: neither may touch the grade
        $this->fakeJudge0($this->accepted("Print a greeting\nAll tasks complete\n"));
        $this->travel(5)->minutes();
        $this->actingAs($this->instructor)->postJson($this->runUrl($session))->assertOk();

        Http::fake(['judge0.test/*' => Http::response(['status' => ['id' => 11, 'description' => 'Runtime Error (NZEC)'], 'exit_code' => 1])]);
        $this->actingAs($this->instructor)->postJson($this->runUrl($session))->assertOk();

        $this->assertEquals($before['session'], DB::table('lab_sessions')->where('id', $session->id)->first());
        $this->assertEquals($before['competencies'], DB::table('student_competencies')->get());
        $this->assertSame($before['telemetry'], TelemetryLog::count());
        $this->assertSame($before['anomalies'], Anomaly::count());
        $this->assertSame($before['notifications'], DB::table('notifications')->count());
    }

    public function test_only_the_classs_instructor_and_admins_may_run_it(): void
    {
        $teammate = $this->user('Teammate Tom', 'student');
        $group = Group::create(['lab_id' => $this->laboratory->id, 'name' => 'Team A']);
        $group->members()->attach([$this->student->id, $teammate->id]);
        $session = $this->submittedSession(['group_id' => $group->id]);
        $otherInstructor = $this->user('Other Instructor', 'instructor');
        $admin = $this->user('Admin Ada', 'admin');
        $this->fakeJudge0($this->accepted());

        foreach ([$this->student, $teammate, $otherInstructor] as $refused) {
            $this->actingAs($refused)->postJson($this->runUrl($session))->assertForbidden();
        }
        Http::assertNothingSent();

        $this->actingAs($admin)->postJson($this->runUrl($session))->assertOk();
        $this->actingAs($this->instructor)->postJson($this->runUrl($session))->assertOk();
        Http::assertSentCount(2);
    }

    public function test_guests_are_sent_to_sign_in(): void
    {
        $session = $this->submittedSession();
        Http::fake();

        $this->post($this->runUrl($session))->assertRedirect();
        $this->postJson($this->runUrl($session))->assertUnauthorized();
        Http::assertNothingSent();
    }

    public function test_without_judge0_nothing_runs_even_where_host_execution_is_allowed(): void
    {
        config(['services.judge0.url' => null, 'services.sandbox.host_execution' => true]);
        $session = $this->submittedSession();
        Http::fake();
        $this->partialMock(SandboxExecutionService::class, fn ($mock) => $mock->shouldNotReceive('execute'));

        $this->actingAs($this->instructor)->postJson($this->runUrl($session))
            ->assertStatus(503)
            ->assertJsonPath('message', 'Code execution (Judge0) is not set up on this server, so the code was not run.');

        Http::assertNothingSent();
    }

    public function test_unreachable_judge0_is_reported_as_not_run(): void
    {
        $session = $this->submittedSession();
        Http::fake(['judge0.test/*' => Http::response('Bad Gateway', 502)]);

        $this->actingAs($this->instructor)->postJson($this->runUrl($session))
            ->assertOk()
            ->assertJson(['status' => 'unavailable', 'output' => '', 'exit_code' => null])
            ->assertJsonPath('errors', 'The code execution service returned an error. Please try again.');
    }

    public function test_attempts_not_handed_in_are_not_run(): void
    {
        $open = $this->submittedSession(['status' => 'in_progress', 'ended_at' => null, 'ai_grade_summary' => null]);
        $timedOut = $this->submittedSession(['status' => 'abandoned', 'ai_grade_summary' => null]);
        Http::fake();

        $this->actingAs($this->instructor)->postJson($this->runUrl($open))->assertStatus(409);
        $this->actingAs($this->instructor)->postJson($this->runUrl($timedOut))->assertStatus(409);

        Http::assertNothingSent();
    }

    public function test_an_attempt_without_code_or_a_known_language_is_not_run(): void
    {
        $empty = $this->submittedSession(['submitted_code' => null, 'submitted_files' => null]);
        $notesOnly = $this->submittedSession(['submitted_code' => null, 'submitted_files' => [['name' => 'README.md', 'content' => '# Notes', 'is_primary' => true]]]);
        $unknown = $this->submittedSession(['submitted_code' => 'print 1', 'submitted_files' => null]);
        Http::fake();

        $this->actingAs($this->instructor)->postJson($this->runUrl($empty))->assertStatus(422);
        $this->actingAs($this->instructor)->postJson($this->runUrl($notesOnly))->assertStatus(422);
        $this->actingAs($this->instructor)->postJson($this->runUrl($unknown))
            ->assertStatus(422)
            ->assertJsonPath('message', "The submission's language is unknown, so it can't be run.");

        Http::assertNothingSent();
    }

    public function test_code_saved_without_a_file_name_runs_in_the_language_it_was_submitted_in(): void
    {
        $session = $this->submittedSession(['submitted_code' => 'print("hi")', 'submitted_files' => null]);
        TelemetryLog::create(['lab_session_id' => $session->id, 'event_type' => 'session_completed', 'payload' => ['language' => 'python']]);
        $this->fakeJudge0($this->accepted("hi\n"));

        $this->actingAs($this->instructor)->postJson($this->runUrl($session))
            ->assertOk()
            ->assertJson(['language' => 'python', 'files' => ['Submitted code']]);

        Http::assertSent(fn (Request $request) => $request['language_id'] === 71 && base64_decode($request['source_code']) === 'print("hi")');
    }

    public function test_a_multi_file_java_submission_is_run_as_one_program(): void
    {
        $main = "package zoo;\n\nimport java.util.List;\n\npublic class Main {\n    public static void main(String[] args) {\n        System.out.println(new Dog().speak());\n    }\n}\n";
        $dog = "package zoo;\n\nimport java.util.List;\nimport java.util.ArrayList;\n\npublic class Dog extends Animal {\n    public String speak() { return \"Woof\"; }\n}\n";
        $animal = "package zoo;\n\npublic abstract class Animal {\n    public abstract String speak();\n}\n";
        $session = $this->submittedSession([
            'submitted_code' => $main,
            'submitted_files' => [
                ['name' => 'Dog.java', 'content' => $dog, 'is_primary' => false],
                ['name' => 'Main.java', 'content' => $main, 'is_primary' => true],
                ['name' => 'Animal.java', 'content' => $animal, 'is_primary' => false],
                ['name' => 'NOTES.md', 'content' => 'Woof notes', 'is_primary' => false],
            ],
        ]);
        $this->fakeJudge0($this->accepted("Woof\n"));

        $this->actingAs($this->instructor)->postJson($this->runUrl($session))
            ->assertOk()
            ->assertJson(['output' => "Woof\n", 'files' => ['Main.java', 'Dog.java', 'Animal.java'], 'skipped_files' => []]);

        Http::assertSent(function (Request $request) {
            $source = base64_decode($request['source_code']);

            return substr_count($source, 'import java.util.List;') === 1
                && str_contains($source, 'import java.util.ArrayList;')
                && ! str_contains($source, 'package zoo;')
                && str_contains($source, 'class Dog extends Animal')
                && ! str_contains($source, 'public class Dog')
                && ! str_contains($source, 'public abstract class Animal')
                && ! str_contains($source, 'Woof notes')
                && strpos($source, 'import java.util.ArrayList;') < strpos($source, 'class Main');
        });
    }

    public function test_other_languages_run_the_main_file_and_say_which_files_were_left_out(): void
    {
        $session = $this->submittedSession([
            'submitted_code' => "from helper import greet\nprint(greet())\n",
            'submitted_files' => [
                ['name' => 'main.py', 'content' => "from helper import greet\nprint(greet())\n", 'is_primary' => true],
                ['name' => 'helper.py', 'content' => "def greet():\n    return 'hi'\n", 'is_primary' => false],
            ],
        ]);
        $this->fakeJudge0($this->accepted("hi\n"));

        $this->actingAs($this->instructor)->postJson($this->runUrl($session))
            ->assertOk()
            ->assertJson(['language' => 'python', 'files' => ['main.py'], 'skipped_files' => ['helper.py']]);

        Http::assertSent(fn (Request $request) => $request['language_id'] === 71 && ! str_contains(base64_decode($request['source_code']), 'def greet'));
    }

    public function test_runs_are_rate_limited_per_instructor(): void
    {
        $session = $this->submittedSession();
        $this->fakeJudge0($this->accepted());

        for ($i = 0; $i < 20; $i++) {
            $this->actingAs($this->instructor)->postJson($this->runUrl($session))->assertOk();
        }
        $this->actingAs($this->instructor)->postJson($this->runUrl($session))->assertStatus(429);

        // Another attempt doesn't reset it: the limit is the instructor's
        $other = $this->submittedSession();
        $this->actingAs($this->instructor)->postJson($this->runUrl($other))->assertStatus(429);
        Http::assertSentCount(20);
    }

    public function test_results_page_shows_the_run_panel_to_staff_only(): void
    {
        $session = $this->submittedSession();
        $runUrl = route('instructor.sessions.run', $session->id);

        $this->actingAs($this->instructor)->get("/sessions/{$session->id}")
            ->assertOk()
            ->assertSee('Run the submitted code')
            ->assertSee('data-submission-runner', false)
            ->assertSee(str_replace('/', '\/', $runUrl), false);

        $this->actingAs($this->student)->get("/sessions/{$session->id}")
            ->assertOk()
            ->assertDontSee('Run the submitted code')
            ->assertDontSee('data-submission-runner', false);
    }

    public function test_results_page_has_no_run_panel_for_an_attempt_not_handed_in(): void
    {
        $session = $this->submittedSession(['status' => 'abandoned', 'ai_grade_summary' => null]);

        $this->actingAs($this->instructor)->get("/sessions/{$session->id}")
            ->assertOk()
            ->assertSee('Submitted files')
            ->assertDontSee('data-submission-runner', false);
    }

    public function test_monitoring_drawer_has_the_run_panel(): void
    {
        $this->submittedSession();

        $this->actingAs($this->instructor)->get("/classes/{$this->schoolClass->id}/telemetry?lab={$this->laboratory->id}")
            ->assertOk()
            ->assertSee('Run the submitted code')
            ->assertSee('data-submission-runner', false)
            ->assertSee('/instructor/sessions/${student.id}/run', false);
    }
}
