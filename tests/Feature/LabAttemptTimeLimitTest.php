<?php

namespace Tests\Feature;

use App\Models\Group;
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
 * When an attempt runs out of time, and which limit applies. An open lab's time per student
 * counts from the start, or from the last reopen, and ends the attempt (abandoned, "Timed
 * out") only after the grace period and never while a submit is grading. A live lab runs on
 * its shared countdown alone, and its end submits the attempt instead.
 */
class LabAttemptTimeLimitTest extends TestCase
{
    use RefreshDatabase;

    private User $student;

    private User $instructor;

    private Laboratory $laboratory;

    private const CODE = ['code' => 'public class Main { public static void main(String[] a) { for (int i = 0; i < 3; i++) { System.out.println(i); } } }', 'language' => 'java'];

    protected function setUp(): void
    {
        parent::setUp();
        $this->freezeSecond();

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

    private function labSession(string $status, \DateTimeInterface $startedAt, ?\DateTimeInterface $lastPingAt = null): LabSession
    {
        return LabSession::create([
            'lab_id' => $this->laboratory->id,
            'user_id' => $this->student->id,
            'status' => $status,
            'started_at' => $startedAt,
            'ended_at' => $status === 'in_progress' ? null : now()->subHours(2),
            'last_ping_at' => $lastPingAt ?? now(),
            'performance_score' => 70.0,
            'completed_tasks' => [],
        ]);
    }

    private function liveLab(int $timePerStudent, int $countdownMinutes, int $minutesAgo): void
    {
        $this->laboratory->update([
            'time_limit' => $timePerStudent,
            'availability_mode' => 'live',
            'live_status' => 'active',
            'live_duration_minutes' => $countdownMinutes,
            'live_started_at' => now()->subMinutes($minutesAgo),
            'live_elapsed_seconds' => 0,
        ]);
    }

    /**
     * Make the grader do something mid-grade (as other requests would), then grade 100%.
     */
    private function graderThat(callable $duringGrading): void
    {
        $this->app->instance(LlmEvaluationService::class, new class($duringGrading) extends LlmEvaluationService
        {
            public function __construct(private $duringGrading) {}

            public function evaluate(LabSession $session, string $code, string $language, array $diagnostics = [], array $otherFiles = []): array
            {
                ($this->duringGrading)($session);

                return ['correctness_score' => 100.0, 'tasks' => [['id' => 1, 'completed' => true]]];
            }
        });
    }

    /**
     * A taken database lock makes Laravel try an INSERT and catch the duplicate-key error.
     * Postgres aborts the surrounding transaction on that error, and in tests that is
     * RefreshDatabase's, so there locks get their own connection.
     */
    private function locksOnTheirOwnConnection(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            config([
                'database.connections.test_locks' => config('database.connections.'.config('database.default')),
                'cache.stores.database.lock_connection' => 'test_locks',
            ]);
            Cache::forgetDriver('database');
        }
    }

    private function ping(LabSession $session)
    {
        return $this->actingAs($this->student)->postJson("/api/v1/sessions/{$session->id}/ping");
    }

    private function start()
    {
        return $this->actingAs($this->student)->withCameraCheck($this->laboratory->id)->postJson("/api/v1/labs/{$this->laboratory->id}/start");
    }

    public function test_a_reopened_attempt_gets_its_time_limit_again_from_the_reopen(): void
    {
        // Graded hours ago, so its first start and last heartbeat are long past
        $session = $this->labSession('completed', now()->subHours(3), now()->subHours(2));

        $this->actingAs($this->instructor)->postJson("/api/v1/sessions/{$session->id}/reopen")->assertOk();
        $session->refresh();
        $this->assertSame('in_progress', $session->status);
        $this->assertTrue($session->started_at->equalTo(now()));

        // None of the checks that used to time it out at once do so now
        $this->ping($session)->assertOk()->assertJson(['status' => 'success', 'time_remaining_seconds' => 45 * 60]);
        $this->actingAs($this->student)->getJson("/api/v1/sessions/{$session->id}")->assertJsonPath('time_remaining_seconds', 45 * 60);
        $this->actingAs($this->student)->get(route('laboratories.show', $this->laboratory->id))->assertOk();
        $this->assertSame(0, LabSession::autoExpireStaleSessions($this->laboratory->id));
        $this->start()->assertOk()->assertJsonPath('session.id', $session->id);
        $this->assertSame('in_progress', $session->fresh()->status);

        // So the student can resubmit, and the new grade counts
        $this->actingAs($this->student)->postJson("/api/v1/sessions/{$session->id}/submit", self::CODE)->assertOk();
        $this->assertSame('completed', $session->fresh()->status);
    }

    public function test_the_website_reopen_restarts_the_time_limit_too(): void
    {
        $session = $this->labSession('abandoned', now()->subHours(3), now()->subHours(2));

        $this->actingAs($this->instructor)->post("/instructor/sessions/{$session->id}/reopen")->assertRedirect();

        $this->assertTrue($session->fresh()->started_at->equalTo(now()));
        $this->ping($session)->assertOk()->assertJson(['status' => 'success', 'time_remaining_seconds' => 45 * 60]);
        $this->assertSame(0, LabSession::autoExpireStaleSessions($this->laboratory->id));
        $this->assertSame('in_progress', $session->fresh()->status);
    }

    public function test_time_spent_and_the_leaderboard_count_from_the_reopen(): void
    {
        $session = $this->labSession('completed', now()->subHours(3), now()->subHours(2));
        $this->actingAs($this->instructor)->postJson("/api/v1/sessions/{$session->id}/reopen")->assertOk();

        $this->travel(10)->minutes();
        $this->actingAs($this->student)->postJson("/api/v1/sessions/{$session->id}/submit", self::CODE)
            ->assertOk()
            ->assertJsonPath('summary.time_spent_seconds', 10 * 60);
        $this->actingAs($this->student)->getJson("/api/v1/sessions/{$session->id}/leaderboard")
            ->assertJsonPath('leaderboard.0.elapsed_seconds', 10 * 60);
    }

    /**
     * A team attempt of the student, who leads the team, begun at $teamStartedAt, with the
     * student's own session started then too.
     */
    private function teamSession(string $status, \DateTimeInterface $teamStartedAt): LabSession
    {
        $this->laboratory->update(['is_group_lab' => true]);
        $team = Group::create(['lab_id' => $this->laboratory->id, 'name' => 'Team A', 'leader_id' => $this->student->id]);
        $team->members()->attach([$this->student->id]);

        $session = $this->labSession($status, $teamStartedAt, $status === 'in_progress' ? null : now()->subHours(2));
        $session->update(['group_id' => $team->id, 'team_started_at' => $teamStartedAt]);

        return $session->fresh();
    }

    public function test_a_team_attempt_reopened_after_its_time_ran_out_runs_on_its_own_clock(): void
    {
        // The team's attempt began three hours ago; the instructor reopens it alone
        $session = $this->teamSession('completed', now()->subHours(3)->startOfSecond());

        $this->actingAs($this->instructor)->postJson("/api/v1/sessions/{$session->id}/reopen")->assertOk();

        $session->refresh();
        $this->assertTrue($session->clockStartedAt()->equalTo(now()));
        $this->assertTrue($session->acceptsWork());
        $this->ping($session)->assertOk()->assertJson(['status' => 'success', 'time_remaining_seconds' => 45 * 60]);
        $this->actingAs($this->student)->getJson("/api/v1/sessions/{$session->id}")->assertJsonPath('time_remaining_seconds', 45 * 60);
        $this->assertSame(0, LabSession::autoExpireStaleSessions($this->laboratory->id));
        $this->start()->assertOk()->assertJsonPath('session.id', $session->id);
        $this->assertSame('in_progress', $session->fresh()->status);
    }

    public function test_a_team_attempt_reopened_while_the_teams_time_runs_keeps_the_teams_clock(): void
    {
        // Ten minutes into the team's 45: reopening gives no extra time
        $teamStart = now()->subMinutes(10)->startOfSecond();
        $session = $this->teamSession('abandoned', $teamStart);

        $this->actingAs($this->instructor)->postJson("/api/v1/sessions/{$session->id}/reopen")->assertOk();

        $session->refresh();
        $this->assertTrue($session->clockStartedAt()->equalTo($teamStart));
        $this->ping($session)->assertOk()->assertJson(['status' => 'success', 'time_remaining_seconds' => 35 * 60]);
        $this->actingAs($this->student)->getJson("/api/v1/sessions/{$session->id}")->assertJsonPath('time_remaining_seconds', 35 * 60);
    }

    public function test_a_teams_attempt_times_out_on_the_teams_clock_at_ping(): void
    {
        // The student joined five minutes after the team began: the team's clock decides
        $session = $this->teamSession('in_progress', now()->subMinutes(46)->startOfSecond());
        $session->update(['started_at' => now()->subMinutes(41)]);

        $this->ping($session)->assertOk()->assertJson(['status' => 'abandoned']);
        $this->assertSame('abandoned', $session->fresh()->status);
    }

    public function test_a_lab_without_a_time_limit_never_times_out(): void
    {
        $this->laboratory->update(['time_limit' => 0]);
        $session = $this->labSession('in_progress', now()->subMinutes(90));

        $this->ping($session)->assertOk()->assertJson(['status' => 'success']);
        $this->assertSame(0, LabSession::autoExpireStaleSessions($this->laboratory->id));
        $this->assertSame('in_progress', $session->fresh()->status);
    }

    public function test_a_reopened_attempt_still_times_out_after_its_new_limit(): void
    {
        $session = $this->labSession('completed', now()->subHours(3), now()->subHours(2));
        $this->actingAs($this->instructor)->postJson("/api/v1/sessions/{$session->id}/reopen")->assertOk();

        $this->travel(45 * 60 + LabSession::LATE_GRACE_SECONDS)->seconds();
        $this->ping($session)->assertOk()->assertJson(['status' => 'abandoned']);
        $this->assertSame('abandoned', $session->fresh()->status);
    }

    public function test_a_reopened_live_attempt_keeps_its_start(): void
    {
        $this->liveLab(timePerStudent: 30, countdownMinutes: 90, minutesAgo: 40);
        $session = $this->labSession('completed', now()->subMinutes(40), now()->subMinutes(20));

        $this->actingAs($this->instructor)->postJson("/api/v1/sessions/{$session->id}/reopen")->assertOk();

        $session->refresh();
        $this->assertSame('in_progress', $session->status);
        $this->assertTrue($session->started_at->equalTo(now()->subMinutes(40)));
        $this->ping($session)->assertOk()->assertJson(['status' => 'success', 'time_remaining_seconds' => 50 * 60]);
    }

    public function test_a_live_attempt_runs_on_the_shared_countdown_not_the_time_per_student(): void
    {
        // 30 minutes per student, but a 90-minute countdown with 50 minutes left
        $this->liveLab(timePerStudent: 30, countdownMinutes: 90, minutesAgo: 40);
        $session = $this->labSession('in_progress', now()->subMinutes(40));

        $this->ping($session)->assertOk()->assertJson(['status' => 'success', 'time_remaining_seconds' => 50 * 60]);
        $this->actingAs($this->student)->getJson("/api/v1/sessions/{$session->id}")->assertJsonPath('time_remaining_seconds', 50 * 60);
        $this->assertSame(0, LabSession::autoExpireStaleSessions($this->laboratory->id));
        $this->start()->assertOk()->assertJsonPath('session.id', $session->id);
        $this->assertSame('in_progress', $session->fresh()->status);

        $this->actingAs($this->student)->postJson("/api/v1/sessions/{$session->id}/submit", self::CODE)->assertOk();
        $this->assertSame('completed', $session->fresh()->status);
    }

    public function test_a_ping_after_the_live_countdown_submits_the_attempt_instead_of_timing_it_out(): void
    {
        $this->liveLab(timePerStudent: 30, countdownMinutes: 30, minutesAgo: 31);
        $session = $this->labSession('in_progress', now()->subMinutes(31));

        $this->ping($session)->assertOk()->assertJson(['status' => 'completed', 'is_active' => false]);

        $this->assertSame('completed', $session->fresh()->status);
        $this->assertSame('closed', $this->laboratory->fresh()->live_status);
    }

    public function test_the_sweep_after_the_live_countdown_submits_the_attempt_instead_of_timing_it_out(): void
    {
        $this->liveLab(timePerStudent: 30, countdownMinutes: 30, minutesAgo: 31);
        $session = $this->labSession('in_progress', now()->subMinutes(31));

        $this->assertSame(0, LabSession::autoExpireStaleSessions($this->laboratory->id));

        $this->assertSame('completed', $session->fresh()->status);
        $this->assertSame('closed', $this->laboratory->fresh()->live_status);
    }

    public function test_an_attempt_is_not_timed_out_during_the_grace_period(): void
    {
        // Past the 45-minute limit, but within the grace period submits still get
        $session = $this->labSession('in_progress', now()->subMinutes(45)->subSeconds(30));

        $this->ping($session)->assertOk()->assertJson(['status' => 'success', 'time_remaining_seconds' => 0]);
        $this->assertSame(0, LabSession::autoExpireStaleSessions($this->laboratory->id));
        $this->start()->assertOk()->assertJsonPath('session.id', $session->id);
        $this->assertSame('in_progress', $session->fresh()->status);

        $this->travel(LabSession::LATE_GRACE_SECONDS - 30)->seconds();
        $this->ping($session)->assertOk()->assertJson(['status' => 'abandoned']);
        $this->assertSame('abandoned', $session->fresh()->status);
    }

    public function test_an_attempt_being_graded_is_not_timed_out(): void
    {
        $this->locksOnTheirOwnConnection();
        $session = $this->labSession('in_progress', now()->subMinutes(50));
        $lock = $session->gradingLock();
        $this->assertTrue($lock->get());

        $this->ping($session)->assertOk()->assertJson(['status' => 'success']);
        $this->assertSame(0, LabSession::autoExpireStaleSessions($this->laboratory->id));
        $this->start()->assertOk()->assertJsonPath('session.id', $session->id);
        $this->assertSame('in_progress', $session->fresh()->status);

        // Once grading is over, the attempt times out as usual
        $lock->release();
        $this->ping($session)->assertOk()->assertJson(['status' => 'abandoned']);
        $this->assertSame('abandoned', $session->fresh()->status);
    }

    public function test_a_submit_sent_in_time_lands_when_grading_outlasts_the_grace_period(): void
    {
        $this->locksOnTheirOwnConnection();
        // Sent 10 seconds before the grace period ends; grading takes 20
        $session = $this->labSession('in_progress', now()->subMinutes(45)->subSeconds(LabSession::LATE_GRACE_SECONDS - 10));
        $duringGrading = [];
        $this->graderThat(function (LabSession $s) use (&$duringGrading) {
            $this->travel(20)->seconds();
            // The lab page's sweep and a Start come in meanwhile
            $duringGrading['expired'] = LabSession::autoExpireStaleSessions($s->lab_id);
            $duringGrading['resumed'] = $this->laboratory->findOrStartAttemptFor($this->student)?->id;
            $duringGrading['status'] = LabSession::find($s->id)->status;
        });

        $this->actingAs($this->student)->postJson("/api/v1/sessions/{$session->id}/submit", self::CODE)->assertOk();

        $this->assertSame(['expired' => 0, 'resumed' => $session->id, 'status' => 'in_progress'], $duringGrading);
        $session->refresh();
        $this->assertSame('completed', $session->status);
        $this->assertEquals(100.0, $session->performance_score);
    }
}
