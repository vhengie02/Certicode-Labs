<?php

namespace Tests\Feature;

use App\Models\Group;
use App\Models\Laboratory;
use App\Models\LabSession;
use App\Models\Module;
use App\Models\SchoolClass;
use App\Models\TelemetryLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * The results page a student lands on once a lab attempt ends (and that every lab notification
 * opens): the grade, an instructor's regrade with its reason, the tasks with the grader's
 * feedback, and the session's stats. The instructor's AI summary never reaches it.
 */
class LabResultsPageTest extends TestCase
{
    use RefreshDatabase;

    protected User $instructor;

    protected User $student;

    protected SchoolClass $schoolClass;

    protected Laboratory $laboratory;

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.anthropic.key' => 'mock']);

        $this->instructor = User::create([
            'name' => 'Instructor Doe',
            'email' => 'instructor@example.com',
            'password' => bcrypt('password'),
            'role' => 'instructor',
        ]);

        $this->student = User::create([
            'name' => 'Student Jane',
            'email' => 'student@example.com',
            'password' => bcrypt('password'),
            'role' => 'student',
        ]);

        $this->schoolClass = SchoolClass::create([
            'name' => 'Java Basics',
            'code' => 'JAVA101',
            'instructor_id' => $this->instructor->id,
            'passing_threshold' => 60,
        ]);
        $this->schoolClass->students()->attach($this->student->id, ['status' => 'enrolled']);

        $module = Module::create([
            'class_id' => $this->schoolClass->id,
            'title' => 'Exceptions',
            'content' => 'Syllabus content',
        ]);

        $this->laboratory = Laboratory::create([
            'title' => 'Custom Exceptions',
            'description' => 'Throw your own.',
            'time_limit' => 60,
            'module_id' => $module->id,
            'tasks_definition' => [
                ['id' => 1, 'task' => 'Create InvalidAgeException', 'command' => 'regex:class\s+InvalidAgeException'],
                ['id' => 2, 'task' => 'Throw it from setAge', 'command' => 'regex:throw\s+new\s+InvalidAgeException'],
            ],
        ]);
    }

    private function finishedSession(array $attributes = []): LabSession
    {
        $start = now()->subHours(2);

        return LabSession::create(array_merge([
            'lab_id' => $this->laboratory->id,
            'user_id' => $this->student->id,
            'status' => 'completed',
            'started_at' => $start,
            'ended_at' => $start->copy()->addMinutes(40),
            'performance_score' => 50,
            'completed_tasks' => [1],
        ], $attributes));
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

    public function test_finished_attempt_shows_grade_tasks_feedback_and_stats(): void
    {
        $session = $this->finishedSession([
            'performance_score' => 72.5,
            'wpm' => 0,
            'keystroke_count' => 1200,
            'focus_lost_count' => 3,
            'paste_anomaly_count' => 1,
            'diff_stats' => ['lines_added' => 48, 'lines_deleted' => 6],
            'ai_grade_summary' => [
                'summary' => 'Instructor-only explanation of the grade.',
                'grader_note' => 'Instructor-only grader note.',
                'competencies' => ['exceptions' => ['passed' => true, 'reason' => 'Instructor-only competency reason.']],
                'code_quality_notes' => 'Clean naming.',
                'test_cases_passed' => 1,
                'test_cases_total' => 2,
                'student_feedback' => [
                    'overall' => 'Good start on the exception class.',
                    'code_quality' => 'Clean naming.',
                    'tasks' => [
                        ['id' => 1, 'task' => 'Create InvalidAgeException', 'done' => true, 'feedback' => 'Found the InvalidAgeException class.'],
                        ['id' => 2, 'task' => 'Throw it from setAge', 'done' => false, 'feedback' => 'setAge never throws it.'],
                    ],
                ],
            ],
        ]);

        $this->actingAs($this->student)->get(route('sessions.show', $session->id))
            ->assertOk()
            ->assertSee('Lab results')
            ->assertSee('72.5%')
            ->assertSee('Lab grade')
            ->assertDontSee('Pass mark')
            ->assertSee('Attempt 1')
            ->assertSee('data-local-time="datetime"', false)
            ->assertSee('Create InvalidAgeException')
            ->assertSee('Throw it from setAge')
            ->assertSee('Found the InvalidAgeException class.')
            ->assertSee('setAge never throws it.')
            ->assertSee('Good start on the exception class.')
            ->assertSee('40m 00s')
            ->assertSee('of a 60 min limit')
            ->assertSee('1,200 keystrokes')
            ->assertSee('+48')
            ->assertSee('This grade counts for the lab.')
            ->assertDontSee('Instructor-only explanation of the grade.')
            ->assertDontSee('Instructor-only grader note.')
            ->assertDontSee('Instructor-only competency reason.')
            ->assertDontSee('regex:');
    }

    public function test_regrade_notification_opens_the_results_with_the_new_grade_and_reason(): void
    {
        $session = $this->finishedSession(['performance_score' => 50]);

        $this->actingAs($this->instructor)
            ->postJson("/instructor/sessions/{$session->id}/override-grade", [
                'override_score' => 85,
                'override_reason' => "The logic was right.\nOnly the output format was off.",
            ])
            ->assertOk();

        $notification = $this->student->fresh()->notifications->first();
        $this->assertSame('Lab reviewed: Custom Exceptions', $notification->data['title']);
        $this->assertSame("/sessions/{$session->id}", $notification->data['url']);
        $this->assertStringContainsString('from 50% to 85%', $notification->data['message']);
        $this->assertStringContainsString('Reason: The logic was right.', $notification->data['message']);

        $this->actingAs($this->student)->get($notification->data['url'])
            ->assertOk()
            ->assertSee('85%')
            ->assertSee('Regraded by instructor')
            ->assertSee('Why the grade changed')
            ->assertSee('Instructor Doe')
            ->assertSee('changed the grade from 50% to 85%')
            ->assertSee("The logic was right.\nOnly the output format was off.");
    }

    public function test_every_regrade_is_listed_with_its_reason(): void
    {
        $session = $this->finishedSession(['performance_score' => 50]);

        foreach ([[85, 'First look: logic was right.'], [90, 'Second look: tests pass too.']] as [$score, $reason]) {
            $this->actingAs($this->instructor)
                ->postJson("/instructor/sessions/{$session->id}/override-grade", ['override_score' => $score, 'override_reason' => $reason])
                ->assertOk();
        }

        // Both notifications land in the same second, so their order isn't fixed
        $messages = $this->student->fresh()->notifications->pluck('data.message')->implode("\n");
        $this->assertStringContainsString('from 50% to 85%', $messages);
        $this->assertStringContainsString('from 85% to 90%', $messages);

        $this->actingAs($this->student)->get(route('sessions.show', $session->id))
            ->assertOk()
            ->assertSee('changed the grade from 85% to 90%')
            ->assertSee('Second look: tests pass too.')
            ->assertSee('1 earlier change')
            ->assertSee('set it to 85%')
            ->assertSee('First look: logic was right.');
    }

    public function test_regrade_without_a_reason_says_so(): void
    {
        $session = $this->finishedSession([
            'instructor_grade_override' => 70,
            'instructor_overridden_at' => now(),
            'overridden_by' => $this->instructor->id,
        ]);

        $this->actingAs($this->student)->get(route('sessions.show', $session->id))
            ->assertOk()
            ->assertSee('70%')
            ->assertSee('No reason was given.');
    }

    public function test_submitting_from_vscode_keeps_the_feedback_and_links_to_the_results(): void
    {
        $session = LabSession::create([
            'lab_id' => $this->laboratory->id,
            'user_id' => $this->student->id,
            'status' => 'in_progress',
            'started_at' => now()->subMinutes(10),
        ]);
        $code = "public class InvalidAgeException extends Exception {\n    public InvalidAgeException(String m) { super(m); }\n}\n";

        $response = $this->actingAs($this->student)
            ->postJson("/api/v1/sessions/{$session->id}/submit", ['code' => $code, 'language' => 'java'])
            ->assertOk();
        $this->assertArrayNotHasKey('ai_grade_summary', $response->json('evaluation'));

        // The provisional note is shown from needs_review on the page, not kept in the feedback
        $this->assertStringStartsWith('Provisional grade', $response->json('evaluation.overall_feedback'));
        $feedback = $session->fresh()->studentFeedback();
        $this->assertStringNotContainsString('Provisional grade', $feedback['overall']);
        $this->assertSame(['Create InvalidAgeException', 'Throw it from setAge'], array_column($feedback['tasks'], 'task'));
        $this->assertSame([true, false], array_column($feedback['tasks'], 'done'));
        $this->assertSame(
            array_column($response->json('evaluation.tasks'), 'feedback'),
            array_column($feedback['tasks'], 'feedback')
        );

        $notification = $this->student->fresh()->notifications->first();
        $this->assertSame("/sessions/{$session->id}", $notification->data['url']);

        $this->actingAs($this->student)->get($notification->data['url'])
            ->assertOk()
            ->assertSee($feedback['tasks'][0]['feedback'])
            ->assertSee('Provisional');
    }

    public function test_results_keep_the_checklist_as_graded_after_the_lab_changes(): void
    {
        $session = LabSession::create([
            'lab_id' => $this->laboratory->id,
            'user_id' => $this->student->id,
            'status' => 'in_progress',
            'started_at' => now()->subMinutes(10),
        ]);
        $code = "public class InvalidAgeException extends Exception {\n    public InvalidAgeException(String m) { super(m); }\n}\n";
        $this->actingAs($this->student)
            ->postJson("/api/v1/sessions/{$session->id}/submit", ['code' => $code, 'language' => 'java'])
            ->assertOk();

        // The instructor drops the first task; the lab form renumbers what is left from 1
        $this->laboratory->update(['tasks_definition' => [
            ['id' => 1, 'task' => 'Throw it from setAge', 'command' => 'regex:throw'],
        ]]);

        $this->actingAs($this->student)->get(route('sessions.show', $session->id))
            ->assertOk()
            ->assertSee('Create InvalidAgeException<span class="sr-only">: done</span>', false)
            ->assertSee('Throw it from setAge<span class="sr-only">: not done</span>', false);
    }

    public function test_attempt_still_being_graded_shows_that_until_the_grade_is_in(): void
    {
        // On Postgres a taken lock aborts the surrounding transaction, here RefreshDatabase's.
        // Production checks the lock outside any transaction, so locks get their own connection.
        if (DB::getDriverName() === 'pgsql') {
            config([
                'database.connections.test_locks' => config('database.connections.'.config('database.default')),
                'cache.stores.database.lock_connection' => 'test_locks',
            ]);
            Cache::forgetDriver('database');
        }

        // Time ran out (or a ping closed the attempt) a moment ago, while its submit was grading
        $session = $this->finishedSession(['status' => 'abandoned', 'ai_grade_summary' => null, 'ended_at' => now()]);
        $lock = $session->gradingLock();
        $this->assertTrue($lock->get());

        $this->actingAs($this->student)->get(route('sessions.show', $session->id))
            ->assertOk()
            ->assertSee('Grading your submission')
            ->assertDontSee('Timed out');

        $lock->release();

        $this->actingAs($this->student)->get(route('sessions.show', $session->id))
            ->assertOk()
            ->assertDontSee('Grading your submission')
            ->assertSee('Timed out');
    }

    public function test_an_attempt_that_ended_a_while_ago_skips_the_grading_check(): void
    {
        $session = $this->finishedSession(['status' => 'abandoned', 'ai_grade_summary' => null]);
        Cache::spy();

        $this->actingAs($this->student)->get(route('sessions.show', $session->id))
            ->assertOk()
            ->assertSee('Timed out');

        Cache::shouldNotHaveReceived('store', ['database']);
    }

    public function test_confirming_the_same_grade_says_it_was_kept(): void
    {
        $session = $this->finishedSession([
            'performance_score' => 70,
            'ai_grade_summary' => ['summary' => 'x', 'grader' => 'rule_based', 'needs_review' => true],
        ]);

        $this->actingAs($this->instructor)
            ->postJson("/instructor/sessions/{$session->id}/override-grade", ['override_score' => 70, 'override_reason' => 'Checked the code myself.'])
            ->assertOk();

        $message = $this->student->fresh()->notifications->first()->data['message'];
        $this->assertStringContainsString('confirmed your score of 70%', $message);
        $this->assertStringNotContainsString('changed your score', $message);

        $this->actingAs($this->student)->get(route('sessions.show', $session->id))
            ->assertOk()
            ->assertSee('Reviewed by instructor')
            ->assertSee('Instructor review')
            ->assertSee('reviewed the grade and kept it at 70%')
            ->assertSee('Checked the code myself.')
            ->assertDontSee('changed the grade')
            ->assertDontSee('Provisional');
    }

    public function test_regrade_shows_the_grade_the_student_had_when_it_was_changed(): void
    {
        $session = $this->finishedSession(['performance_score' => 0]);
        $this->actingAs($this->instructor)
            ->postJson("/instructor/sessions/{$session->id}/override-grade", ['override_score' => 80, 'override_reason' => 'Ran it myself.'])
            ->assertOk();

        // Reopened and resubmitted later: the AI grade moves, the regrade stays as it was
        $session->update(['performance_score' => 50]);

        $this->actingAs($this->student)->get(route('sessions.show', $session->id))
            ->assertOk()
            ->assertSee('changed the grade from 0% to 80%');
    }

    public function test_clients_cannot_write_regrade_records(): void
    {
        $session = $this->finishedSession();

        foreach (['instructor_grade_override', 'session_completed', 'webcam_prelab_verification'] as $eventType) {
            $this->actingAs($this->student)
                ->postJson("/api/v1/sessions/{$session->id}/telemetry", [
                    'event_type' => $eventType,
                    'payload' => ['instructor_name' => 'Dean Forged', 'override_score' => 100, 'reason' => 'FORGED REASON'],
                ])
                ->assertUnprocessable()
                ->assertJsonValidationErrors('event_type');
        }
        // Events the client really sends still go through
        $this->actingAs($this->student)
            ->postJson("/api/v1/sessions/{$session->id}/telemetry", ['event_type' => 'focus_lost', 'payload' => []])
            ->assertOk();

        $this->assertSame(['focus_lost'], TelemetryLog::where('lab_session_id', $session->id)->pluck('event_type')->all());
        $this->actingAs($this->student)->get(route('sessions.show', $session->id))
            ->assertOk()
            ->assertDontSee('Dean Forged');
    }

    public function test_a_malformed_regrade_record_does_not_break_the_page(): void
    {
        $session = $this->finishedSession([
            'instructor_grade_override' => 75,
            'instructor_override_reason' => 'Fair.',
            'instructor_overridden_at' => now(),
            'overridden_by' => $this->instructor->id,
        ]);
        TelemetryLog::create(['lab_session_id' => $session->id, 'event_type' => 'instructor_grade_override', 'payload' => [
            'timestamp' => 'not-a-date', 'reason' => ['not', 'text'], 'instructor_name' => ['x'], 'override_score' => 'abc',
        ]]);
        TelemetryLog::create(['lab_session_id' => $session->id, 'event_type' => 'instructor_grade_override', 'payload' => [
            'instructor_name' => 'Instructor Doe', 'override_score' => 75, 'reason' => 'Fair.', 'timestamp' => now()->toIso8601String(),
        ]]);

        $this->actingAs($this->instructor)->get(route('sessions.show', $session->id))
            ->assertOk()
            ->assertSee('1 earlier change')
            ->assertSee('Fair.');
    }

    public function test_completing_on_the_website_opens_the_results(): void
    {
        $session = LabSession::create([
            'lab_id' => $this->laboratory->id,
            'user_id' => $this->student->id,
            'status' => 'in_progress',
            'started_at' => now(),
        ]);

        $this->actingAs($this->student)->post("/sessions/{$session->id}/complete")
            ->assertRedirect(route('sessions.show', $session->id));
    }

    public function test_attempt_in_progress_opens_its_lab_page(): void
    {
        $session = LabSession::create([
            'lab_id' => $this->laboratory->id,
            'user_id' => $this->student->id,
            'status' => 'in_progress',
            'started_at' => now(),
        ]);

        $this->actingAs($this->student)->get(route('sessions.show', $session->id))
            ->assertRedirect(route('laboratories.show', $this->laboratory->id));
    }

    public function test_timed_out_attempt_is_not_graded(): void
    {
        $session = $this->finishedSession(['status' => 'abandoned', 'performance_score' => 40]);

        $this->actingAs($this->student)->get(route('sessions.show', $session->id))
            ->assertOk()
            ->assertSee('Not graded')
            ->assertSee('Timed out')
            ->assertSee("This attempt timed out before it was submitted, so it doesn't count toward the lab grade.", false);
    }

    public function test_attempt_ended_without_a_submit_says_where_its_grade_came_from(): void
    {
        $session = $this->finishedSession(['ai_grade_summary' => null, 'performance_score' => 50]);

        $this->actingAs($this->student)->get(route('sessions.show', $session->id))
            ->assertOk()
            ->assertSee('This attempt ended before it was submitted, so the grade comes from the progress saved when it ended.');
    }

    public function test_an_earlier_attempt_points_to_the_best_one(): void
    {
        $first = $this->finishedSession(['performance_score' => 55]);
        $best = $this->finishedSession(['performance_score' => 80]);

        $this->actingAs($this->student)->get(route('sessions.show', $first->id))
            ->assertOk()
            ->assertSee('The best grade on this lab is 80%')
            ->assertSee('href="'.route('sessions.show', $best->id).'"', false);

        $this->actingAs($this->student)->get(route('sessions.show', $best->id))
            ->assertOk()
            ->assertSee('Best attempt')
            ->assertSee('This is the best of the completed attempts, so this grade is the one that counts.');
    }

    public function test_team_members_and_class_staff_can_open_the_results_but_no_one_else(): void
    {
        $teammate = $this->user('Team Mate', 'student');
        $outsider = $this->user('Other Student', 'student');
        $otherInstructor = $this->user('Other Instructor', 'instructor');
        $admin = $this->user('Site Admin', 'admin');
        $this->schoolClass->students()->attach([$teammate->id, $outsider->id], ['status' => 'enrolled']);

        $group = Group::create(['lab_id' => $this->laboratory->id, 'name' => 'Team A']);
        $group->members()->attach([$this->student->id, $teammate->id]);
        $session = $this->finishedSession(['group_id' => $group->id]);
        $url = route('sessions.show', $session->id);

        // The teammate has no session of their own, only the group membership
        $this->actingAs($teammate)->get($url)->assertOk()->assertSee('Team A')->assertSee('Team Mate');
        $this->actingAs($this->instructor)->get($url)->assertOk()->assertSee('Student Jane');
        $this->actingAs($admin)->get($url)->assertOk();
        $this->actingAs($outsider)->get($url)->assertForbidden();
        $this->actingAs($otherInstructor)->get($url)->assertForbidden();
    }

    public function test_a_teammate_sees_only_team_attempts_and_not_the_owners_own_figures(): void
    {
        $teammate = $this->user('Team Mate', 'student');
        $this->schoolClass->students()->attach($teammate->id, ['status' => 'enrolled']);
        $group = Group::create(['lab_id' => $this->laboratory->id, 'name' => 'Team A']);
        $group->members()->attach([$this->student->id, $teammate->id]);

        $this->finishedSession(['performance_score' => 33]);
        $team = $this->finishedSession(['group_id' => $group->id, 'performance_score' => 64, 'focus_lost_count' => 17, 'keystroke_count' => 4321]);
        $url = route('sessions.show', $team->id);

        $this->actingAs($teammate)->get($url)
            ->assertOk()
            ->assertSee('64%')
            ->assertDontSee('33%')
            ->assertDontSee('Focus lost')
            ->assertDontSee('4,321');

        $this->actingAs($this->student)->get($url)
            ->assertOk()
            ->assertSee('33%')
            ->assertSee('Focus lost')
            ->assertSee('4,321');
    }

    public function test_guests_are_sent_to_log_in(): void
    {
        $session = $this->finishedSession();

        $this->get(route('sessions.show', $session->id))->assertRedirect(route('login'));
    }

    public function test_lab_page_lists_finished_attempts_linking_to_their_results(): void
    {
        $regraded = $this->finishedSession([
            'instructor_grade_override' => 85,
            'instructor_override_reason' => 'Logic was right.',
            'instructor_overridden_at' => now(),
            'overridden_by' => $this->instructor->id,
        ]);
        $timedOut = $this->finishedSession(['status' => 'abandoned']);
        $open = LabSession::create([
            'lab_id' => $this->laboratory->id,
            'user_id' => $this->student->id,
            'status' => 'in_progress',
            'started_at' => now(),
        ]);

        $this->actingAs($this->student)->get(route('laboratories.show', $this->laboratory->id))
            ->assertOk()
            ->assertSee('Your results')
            ->assertSee('href="'.route('sessions.show', $regraded->id).'"', false)
            ->assertSee('href="'.route('sessions.show', $timedOut->id).'"', false)
            ->assertDontSee('href="'.route('sessions.show', $open->id).'"', false)
            ->assertSee('Attempt 1')
            ->assertSee('Attempt 2')
            ->assertSee('regraded')
            ->assertSee('Timed out');
    }

    public function test_lab_page_has_no_results_card_before_any_attempt_ends(): void
    {
        $this->actingAs($this->student)->get(route('laboratories.show', $this->laboratory->id))
            ->assertOk()
            ->assertDontSee('Your results');
    }
}
