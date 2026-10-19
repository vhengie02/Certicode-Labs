<?php

namespace Tests\Feature;

use App\Models\Laboratory;
use App\Models\LabSession;
use App\Models\Module;
use App\Models\SchoolClass;
use App\Models\User;
use App\Services\LlmEvaluationService;
use App\Services\SandboxExecutionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Regression tests for regrading and ungraded finishing: once an attempt is over (submitted,
 * timed out, live lab closed) its grade stands; two grading requests never run at once; and
 * leaving the lab doesn't finalize the attempt.
 */
class LabAttemptLifecycleGuardTest extends TestCase
{
    use RefreshDatabase;

    private User $student;

    private User $instructor;

    private Laboratory $laboratory;

    private const CODE = ['code' => 'public class Main { public static void main(String[] a) { for (int i = 0; i < 3; i++) { System.out.println(i); } } }', 'language' => 'java'];

    protected function setUp(): void
    {
        parent::setUp();

        $this->student = User::create(['name' => 'Student', 'email' => 'student@example.com', 'password' => bcrypt('password'), 'role' => 'student']);
        $this->instructor = User::create(['name' => 'Prof', 'email' => 'prof@example.com', 'password' => bcrypt('password'), 'role' => 'instructor']);
        $class = SchoolClass::create(['name' => 'Java 101', 'code' => 'JAVA-101', 'instructor_id' => $this->instructor->id]);
        $class->students()->attach($this->student->id, ['status' => 'enrolled']);
        $module = Module::create(['class_id' => $class->id, 'title' => 'Module', 'content' => 'Content']);

        $this->laboratory = Laboratory::create([
            'module_id' => $module->id,
            'title' => 'Loops',
            'description' => 'Write a loop.',
            'time_limit' => 45,
            'is_group_lab' => false,
            'tasks_definition' => [['id' => 1, 'task' => 'Use a for loop']],
        ]);

        $this->mock(SandboxExecutionService::class)
            ->shouldReceive('execute')
            ->andReturn(['status' => 'success', 'output' => '', 'execution_time_ms' => 1]);
    }

    private function labSession(string $status = 'in_progress', ?\DateTimeInterface $startedAt = null, float $score = 40.0): LabSession
    {
        return LabSession::create([
            'lab_id' => $this->laboratory->id,
            'user_id' => $this->student->id,
            'status' => $status,
            'started_at' => $startedAt ?? now(),
            'ended_at' => $status === 'in_progress' ? null : now()->subMinute(),
            'performance_score' => $score,
            'completed_tasks' => [],
        ]);
    }

    /**
     * Make the grader do something mid-grade (as another request would), then grade 100%.
     */
    private function graderThat(callable $duringGrading): void
    {
        $this->app->instance(LlmEvaluationService::class, new class($duringGrading) extends LlmEvaluationService
        {
            public function __construct(private $duringGrading) {}

            public function evaluate(LabSession $session, string $code, string $language, array $diagnostics = []): array
            {
                ($this->duringGrading)($session);

                return ['correctness_score' => 100.0, 'tasks' => [['id' => 1, 'completed' => true]]];
            }
        });
    }

    public function test_a_finished_attempt_cannot_be_regraded(): void
    {
        foreach (['completed', 'abandoned'] as $status) {
            $session = $this->labSession($status);

            foreach (['submit', 'check-progress'] as $endpoint) {
                $this->actingAs($this->student)
                    ->postJson("/api/v1/sessions/{$session->id}/{$endpoint}", self::CODE)
                    ->assertStatus(409)
                    ->assertJson(['error' => 'session_not_in_progress']);
            }

            $session->refresh();
            $this->assertSame($status, $session->status);
            $this->assertEquals(40.0, $session->performance_score);
            $this->assertSame([], $session->completed_tasks);
        }
    }

    public function test_work_past_the_time_limit_is_refused_after_a_short_grace(): void
    {
        $late = $this->labSession('in_progress', now()->subMinutes(47));
        $this->actingAs($this->student)->postJson("/api/v1/sessions/{$late->id}/submit", self::CODE)->assertStatus(409);
        $this->assertSame('in_progress', $late->fresh()->status);

        // A student has one open attempt per lab (unique index), so end that one first
        $late->update(['status' => 'abandoned']);
        $justInTime = $this->labSession('in_progress', now()->subMinutes(45)->subSeconds(30));
        $this->actingAs($this->student)->postJson("/api/v1/sessions/{$justInTime->id}/submit", self::CODE)->assertOk();
        $this->assertSame('completed', $justInTime->fresh()->status);
    }

    public function test_work_after_the_live_lab_ends_is_refused(): void
    {
        $this->laboratory->update(['availability_mode' => 'live', 'live_status' => 'closed', 'live_duration_minutes' => 30, 'live_elapsed_seconds' => 1800]);
        $session = $this->labSession();

        $this->actingAs($this->student)->postJson("/api/v1/sessions/{$session->id}/check-progress", self::CODE)->assertStatus(409);
        $this->actingAs($this->student)->postJson("/api/v1/sessions/{$session->id}/submit", self::CODE)->assertStatus(409);
        $this->assertEquals(40.0, $session->fresh()->performance_score);
    }

    public function test_a_second_grading_request_waits_for_the_first(): void
    {
        // A taken database lock makes Laravel try an INSERT and catch the duplicate-key error.
        // Postgres aborts the surrounding transaction on that error, and in tests that is
        // RefreshDatabase's. Production takes the lock outside any transaction, so locks get
        // their own connection here.
        if (DB::getDriverName() === 'pgsql') {
            config([
                'database.connections.test_locks' => config('database.connections.'.config('database.default')),
                'cache.stores.database.lock_connection' => 'test_locks',
            ]);
            // The rate limiter already built the store at boot, with the old lock connection
            Cache::forgetDriver('database');
        }

        $session = $this->labSession();
        $lock = Cache::store('database')->lock("lab_session_{$session->id}_grading", 90);
        $this->assertTrue($lock->get());

        $this->actingAs($this->student)
            ->postJson("/api/v1/sessions/{$session->id}/submit", self::CODE)
            ->assertStatus(409)
            ->assertJson(['error' => 'grading_in_progress']);
        $this->assertSame('in_progress', $session->fresh()->status);

        $lock->release();
        $this->actingAs($this->student)->postJson("/api/v1/sessions/{$session->id}/submit", self::CODE)->assertOk();
        $this->assertSame('completed', $session->fresh()->status);
    }

    public function test_a_check_that_finishes_after_the_attempt_closed_does_not_overwrite_it(): void
    {
        $session = $this->labSession();
        $this->graderThat(fn (LabSession $s) => LabSession::whereKey($s->id)->update(['status' => 'completed', 'ended_at' => now(), 'performance_score' => 55]));

        $this->actingAs($this->student)->postJson("/api/v1/sessions/{$session->id}/check-progress", self::CODE)->assertStatus(409);

        $session->refresh();
        $this->assertEquals(55.0, $session->performance_score);
        $this->assertSame([], $session->completed_tasks);
    }

    public function test_a_submit_sent_in_time_still_counts_when_time_runs_out_mid_grade(): void
    {
        $session = $this->labSession();
        $this->graderThat(fn (LabSession $s) => LabSession::whereKey($s->id)->update(['status' => 'abandoned', 'ended_at' => now()]));

        $this->actingAs($this->student)->postJson("/api/v1/sessions/{$session->id}/submit", self::CODE)->assertOk();

        $session->refresh();
        $this->assertSame('completed', $session->status);
        $this->assertEquals(100.0, $session->performance_score);
    }

    public function test_leaving_the_lab_keeps_the_attempt_open_to_resume_and_submit(): void
    {
        $session = $this->labSession();

        $this->actingAs($this->student)->postJson("/api/v1/sessions/{$session->id}/end")->assertOk();
        $this->assertSame('in_progress', $session->fresh()->status);
        $this->assertDatabaseHas('telemetry_logs', ['lab_session_id' => $session->id, 'event_type' => 'session_left']);

        // Starting again resumes the same attempt instead of hitting attempts_exhausted
        $this->actingAs($this->student)->postJson("/api/v1/labs/{$this->laboratory->id}/start")
            ->assertOk()
            ->assertJsonPath('session.id', $session->id);

        $this->actingAs($this->student)->postJson("/api/v1/sessions/{$session->id}/submit", self::CODE)->assertOk();
        $this->assertSame('completed', $session->fresh()->status);
    }

    public function test_staff_ending_a_session_still_closes_it(): void
    {
        $session = $this->labSession();

        $this->actingAs($this->instructor)->postJson("/api/v1/sessions/{$session->id}/end")->assertOk();
        $this->assertSame('completed', $session->fresh()->status);
    }

    public function test_the_website_complete_route_cannot_revive_a_timed_out_attempt(): void
    {
        $session = $this->labSession('abandoned');

        $this->actingAs($this->student)->post("/sessions/{$session->id}/complete")->assertRedirect();
        $this->assertSame('abandoned', $session->fresh()->status);
    }
}
