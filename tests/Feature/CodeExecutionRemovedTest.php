<?php

namespace Tests\Feature;

use App\Models\Laboratory;
use App\Models\LabSession;
use App\Models\Module;
use App\Models\SchoolClass;
use App\Models\TelemetryLog;
use App\Models\User;
use App\Services\LlmEvaluationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Student code is no longer run anywhere (Judge0 and the browser Run panel were removed).
 * Grading never depended on it; these tests keep the run paths from coming back by accident.
 */
class CodeExecutionRemovedTest extends TestCase
{
    use RefreshDatabase;

    private User $student;

    private User $instructor;

    private Laboratory $laboratory;

    protected function setUp(): void
    {
        parent::setUp();

        $this->student = User::create(['name' => 'Student', 'email' => 'student@example.com', 'password' => bcrypt('password'), 'role' => 'student']);
        $this->instructor = User::create(['name' => 'Prof', 'email' => 'prof@example.com', 'password' => bcrypt('password'), 'role' => 'instructor']);
        $class = SchoolClass::create(['name' => 'Java 101', 'code' => 'JAVA-101', 'instructor_id' => $this->instructor->id]);
        $class->students()->attach($this->student->id, ['status' => 'enrolled']);
        $module = Module::create(['class_id' => $class->id, 'title' => 'Module', 'content' => 'Content']);
        $this->laboratory = Laboratory::create(['module_id' => $module->id, 'title' => 'Print', 'description' => 'Print 1.', 'time_limit' => 45, 'is_group_lab' => false, 'tasks_definition' => [['id' => 1, 'task' => 'Print 1']]]);
    }

    private function labSession(string $status): LabSession
    {
        return LabSession::create(['lab_id' => $this->laboratory->id, 'user_id' => $this->student->id, 'status' => $status, 'started_at' => now(), 'performance_score' => 0]);
    }

    public function test_the_student_execute_endpoints_are_gone(): void
    {
        $session = $this->labSession('in_progress');
        $code = ['code' => '<?php echo "hello";', 'language' => 'php'];

        $this->actingAs($this->student)->postJson("/api/v1/sessions/{$session->id}/execute", $code)->assertNotFound();
        $this->assertSame(0, TelemetryLog::where('lab_session_id', $session->id)->count());
    }

    public function test_the_instructor_run_endpoint_is_gone(): void
    {
        $session = $this->labSession('completed');

        $this->actingAs($this->instructor)->postJson("/instructor/sessions/{$session->id}/run")->assertNotFound();
    }

    public function test_the_results_page_has_no_run_panel(): void
    {
        $session = LabSession::create([
            'lab_id' => $this->laboratory->id,
            'user_id' => $this->student->id,
            'status' => 'completed',
            'started_at' => now()->subHour(),
            'ended_at' => now()->subMinutes(20),
            'performance_score' => 80,
            'submitted_code' => 'public class Main {}',
        ]);

        $this->actingAs($this->instructor)->get(route('sessions.show', $session->id))
            ->assertOk()
            ->assertSee('public class Main {}')
            ->assertDontSee('submissionRunner')
            ->assertDontSee('Run code');
    }

    public function test_a_submit_is_graded_without_running_the_code(): void
    {
        $this->app->instance(LlmEvaluationService::class, new class extends LlmEvaluationService
        {
            public function __construct() {}

            public function evaluate(LabSession $session, string $code, string $language, array $diagnostics = [], array $otherFiles = []): array
            {
                return ['correctness_score' => 100.0, 'tasks' => [['id' => 1, 'completed' => true]]];
            }
        });
        $session = $this->labSession('in_progress');

        $response = $this->actingAs($this->student)->postJson("/api/v1/sessions/{$session->id}/submit", [
            'code' => 'public class Main { public static void main(String[] a) { System.out.println(1); } }',
            'language' => 'java',
        ])->assertOk();

        $response->assertJsonMissingPath('execution');
        $this->assertSame('completed', $session->fresh()->status);

        $payload = TelemetryLog::where('lab_session_id', $session->id)->where('event_type', 'session_completed')->sole()->payload;
        $this->assertArrayNotHasKey('execution_status', $payload);
        $this->assertArrayNotHasKey('execution_error', $payload);
    }

    public function test_a_client_still_cannot_write_a_code_execution_row(): void
    {
        $session = $this->labSession('in_progress');

        $this->actingAs($this->student)->postJson("/api/v1/sessions/{$session->id}/telemetry", [
            'event_type' => 'code_execution',
            'payload' => ['status' => 'success'],
        ])->assertUnprocessable()->assertJsonValidationErrors('event_type');

        $this->assertSame(0, TelemetryLog::where('lab_session_id', $session->id)->count());
    }
}
