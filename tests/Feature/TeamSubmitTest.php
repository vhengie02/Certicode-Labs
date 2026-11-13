<?php

namespace Tests\Feature;

use App\Models\Competency;
use App\Models\Group;
use App\Models\Laboratory;
use App\Models\LabSession;
use App\Models\Module;
use App\Models\SchoolClass;
use App\Models\StudentCompetency;
use App\Models\TelemetryLog;
use App\Models\User;
use App\Services\LabTeams;
use App\Services\LlmEvaluationService;
use App\Services\SandboxExecutionService;
use Carbon\CarbonInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * On a group lab the team hands in one piece of work: only the leader submits, the AI grades
 * once, and every teammate who took part in that attempt gets the result.
 */
class TeamSubmitTest extends TestCase
{
    use RefreshDatabase;

    protected User $alice;

    protected User $bob;

    protected User $carol;

    protected Laboratory $laboratory;

    protected Group $team;

    /** When the team attempt under test started: the clock every teammate's session shares. */
    protected CarbonInterface $teamStart;

    private int $gradeCalls = 0;

    /** @var (callable(): void)|null Runs while the AI is grading */
    private $duringGrading = null;

    private const CODE = ['code' => 'public class Main { public static void main(String[] a) { System.out.println(1); } }', 'language' => 'java'];

    protected function setUp(): void
    {
        parent::setUp();

        $instructor = User::create(['name' => 'Prof', 'email' => 'prof@example.com', 'password' => bcrypt('password'), 'role' => 'instructor']);
        $this->alice = User::create(['name' => 'Alice', 'email' => 'alice@example.com', 'password' => bcrypt('password'), 'role' => 'student']);
        $this->bob = User::create(['name' => 'Bob', 'email' => 'bob@example.com', 'password' => bcrypt('password'), 'role' => 'student']);
        $this->carol = User::create(['name' => 'Carol', 'email' => 'carol@example.com', 'password' => bcrypt('password'), 'role' => 'student']);

        $class = SchoolClass::create(['name' => 'Java 101', 'code' => 'JAVA-101', 'instructor_id' => $instructor->id]);
        foreach ([$this->alice, $this->bob, $this->carol] as $student) {
            $class->students()->attach($student->id, ['status' => 'enrolled']);
        }
        $module = Module::create(['class_id' => $class->id, 'title' => 'Module', 'content' => 'Content']);
        $this->laboratory = Laboratory::create([
            'module_id' => $module->id,
            'title' => 'Team Lab',
            'description' => 'Print 1.',
            'time_limit' => 45,
            'is_group_lab' => true,
            'tasks_definition' => [['id' => 1, 'task' => 'Print 1']],
        ]);

        $this->team = Group::create(['lab_id' => $this->laboratory->id, 'name' => 'Team A', 'leader_id' => $this->alice->id]);
        $this->team->members()->attach([$this->alice->id, $this->bob->id, $this->carol->id]);
        $this->teamStart = now()->subMinutes(10)->startOfSecond();

        $this->mock(SandboxExecutionService::class)
            ->shouldReceive('execute')
            ->andReturn(['status' => 'success', 'output' => '1', 'errors' => null, 'execution_time_ms' => 5]);

        $test = $this;
        $this->app->instance(LlmEvaluationService::class, new class($test) extends LlmEvaluationService
        {
            public function __construct(private $test) {}

            public function evaluate(LabSession $session, string $code, string $language, array $diagnostics = [], array $otherFiles = []): array
            {
                $this->test->graderRan();

                return ['correctness_score' => 80.0, 'tasks' => [['id' => 1, 'completed' => true]]];
            }
        });
    }

    public function graderRan(): void
    {
        $this->gradeCalls++;
        if ($this->duringGrading) {
            ($this->duringGrading)();
        }
    }

    /** A teammate's session in the team attempt under test, unless $attributes say otherwise. */
    private function open(User $student, array $attributes = []): LabSession
    {
        return LabSession::create(array_merge([
            'lab_id' => $this->laboratory->id,
            'user_id' => $student->id,
            'group_id' => $this->team->id,
            'team_started_at' => $this->teamStart,
            'status' => 'in_progress',
            'started_at' => $this->teamStart,
            'performance_score' => 0,
        ], $attributes));
    }

    private function submitAs(User $student, LabSession $session, array $payload = self::CODE)
    {
        return $this->actingAs($student)->postJson("/api/v1/sessions/{$session->id}/submit", $payload);
    }

    public function test_only_the_leader_can_submit_a_team_lab()
    {
        $alice = $this->open($this->alice);
        $bob = $this->open($this->bob);

        $this->submitAs($this->bob, $bob)
            ->assertStatus(403)
            ->assertJson(['error' => 'not_team_leader'])
            ->assertJsonPath('message', "Only your team leader, Alice, can submit this lab. Update the CertiCode Labs extension if you don't see who leads your team.");

        $this->assertSame('in_progress', $bob->fresh()->status);
        $this->assertSame('in_progress', $alice->fresh()->status);
        $this->assertSame(0, $this->gradeCalls);
    }

    public function test_a_teammate_cannot_submit_through_the_leaders_session()
    {
        $alice = $this->open($this->alice);
        $this->open($this->bob);

        $this->submitAs($this->bob, $alice, ['code' => 'class Bobs {}', 'language' => 'java'])
            ->assertStatus(403)
            ->assertJson(['error' => 'not_your_attempt']);

        $this->assertSame('in_progress', $alice->fresh()->status);
        $this->assertNull($alice->fresh()->submitted_code);
        $this->assertSame(0, $this->gradeCalls);
    }

    public function test_a_teammate_cannot_check_progress_through_another_teammates_session()
    {
        $alice = $this->open($this->alice);
        $this->open($this->bob);

        $this->actingAs($this->bob)->postJson("/api/v1/sessions/{$alice->id}/check-progress", self::CODE)
            ->assertStatus(403)
            ->assertJson(['error' => 'not_your_attempt']);

        $this->assertSame(0, $this->gradeCalls);
    }

    public function test_a_teammate_cannot_finish_their_attempt_on_their_own_from_the_website()
    {
        $this->open($this->alice);
        $bob = $this->open($this->bob, ['performance_score' => 100]);

        $this->actingAs($this->bob)->post(route('sessions.complete', $bob->id))->assertSessionHas('error');

        $this->assertSame('in_progress', $bob->fresh()->status);
    }

    public function test_the_leader_cannot_finish_the_teams_attempt_from_the_website_either()
    {
        $alice = $this->open($this->alice);

        $this->actingAs($this->alice)->post(route('sessions.complete', $alice->id))->assertSessionHas('error');

        $this->assertSame('in_progress', $alice->fresh()->status);
    }

    public function test_the_leaders_submit_grades_every_teammate_the_same()
    {
        $alice = $this->open($this->alice);
        $bob = $this->open($this->bob);

        $this->submitAs($this->alice, $alice)
            ->assertOk()
            ->assertJsonPath('performance_score', 80)
            ->assertJsonPath('team.submitted_by', $this->alice->id)
            ->assertJsonPath('team.graded_user_ids', [$this->alice->id, $this->bob->id]);

        $leader = $alice->fresh();
        foreach ([$leader, $bob->fresh()] as $session) {
            $this->assertSame('completed', $session->status);
            $this->assertSame(80.0, (float) $session->performance_score);
            $this->assertSame([1], $session->completed_tasks);
            $this->assertSame(self::CODE['code'], $session->submitted_code);
            $this->assertEquals($leader->ended_at, $session->ended_at);
            $this->assertEquals($leader->ai_grade_summary, $session->ai_grade_summary);
        }
        $this->assertSame(1, $this->gradeCalls);
    }

    public function test_every_graded_teammate_gets_a_completion_record_naming_the_submitter()
    {
        $alice = $this->open($this->alice);
        $bob = $this->open($this->bob);

        $this->submitAs($this->alice, $alice)->assertOk();

        foreach ([$alice, $bob] as $session) {
            $log = TelemetryLog::where('lab_session_id', $session->id)->where('event_type', 'session_completed')->sole();
            $this->assertSame($this->alice->id, $log->payload['submitted_by']);
            $this->assertEquals(80.0, $log->payload['final_score']);
        }
    }

    public function test_a_teammate_who_never_opened_the_lab_gets_nothing()
    {
        $alice = $this->open($this->alice);

        $this->submitAs($this->alice, $alice)->assertOk()->assertJsonPath('team.graded_user_ids', [$this->alice->id]);

        $this->assertSame(0, LabSession::where('user_id', $this->carol->id)->count());
    }

    public function test_a_teammate_closed_for_inactivity_during_the_attempt_is_graded()
    {
        $alice = $this->open($this->alice);
        $leftAt = now()->subMinutes(5)->startOfSecond();
        $bob = $this->open($this->bob, ['status' => 'abandoned', 'ended_at' => $leftAt]);

        $this->submitAs($this->alice, $alice)->assertOk();

        $this->assertSame('completed', $bob->fresh()->status);
        $this->assertSame(80.0, (float) $bob->fresh()->performance_score);
        // When they stopped working stays as it was, so their time spent isn't inflated
        $this->assertEquals($leftAt, $bob->fresh()->ended_at);
    }

    public function test_the_teams_earlier_timed_out_attempt_is_left_alone()
    {
        $this->laboratory->update(['max_attempts' => 3]);
        $earlier = now()->subHours(2)->startOfSecond();
        // Closed in the very second the new attempt began, as reopening the lab does
        $oldAlice = $this->open($this->alice, ['team_started_at' => $earlier, 'started_at' => $earlier, 'status' => 'abandoned', 'ended_at' => $this->teamStart, 'submitted_code' => 'old']);
        $oldBob = $this->open($this->bob, ['team_started_at' => $earlier, 'started_at' => $earlier, 'status' => 'abandoned', 'ended_at' => $this->teamStart, 'submitted_code' => 'old']);
        $alice = $this->open($this->alice);

        $this->submitAs($this->alice, $alice)->assertOk()->assertJsonPath('team.graded_user_ids', [$this->alice->id]);

        foreach ([$oldAlice->fresh(), $oldBob->fresh()] as $old) {
            $this->assertSame('abandoned', $old->status);
            $this->assertSame(0.0, (float) $old->performance_score);
            $this->assertSame('old', $old->submitted_code);
        }
    }

    public function test_a_teammate_who_dropped_out_and_came_back_is_graded_once()
    {
        $this->laboratory->update(['max_attempts' => 3]);
        $alice = $this->open($this->alice);
        $dropped = $this->open($this->bob, ['status' => 'abandoned', 'ended_at' => now()->subMinutes(6)]);
        $back = $this->open($this->bob, ['started_at' => now()->subMinutes(4)]);

        $this->submitAs($this->alice, $alice)->assertOk()->assertJsonPath('team.graded_user_ids', [$this->alice->id, $this->bob->id]);

        $this->assertSame('completed', $back->fresh()->status);
        $this->assertSame('abandoned', $dropped->fresh()->status);
    }

    public function test_a_teammate_closed_while_the_submit_was_grading_still_gets_the_teams_grade()
    {
        $alice = $this->open($this->alice);
        $bob = $this->open($this->bob);
        // A live lab closing does this to every open attempt, with a task-count score
        $this->duringGrading = fn () => $bob->update(['status' => 'completed', 'ended_at' => now(), 'performance_score' => 10]);

        $this->submitAs($this->alice, $alice)->assertOk();

        $this->assertSame(80.0, (float) $bob->fresh()->performance_score);
    }

    public function test_a_teammates_finished_earlier_attempt_is_left_alone()
    {
        $earlier = now()->subDay()->startOfSecond();
        $finished = $this->open($this->bob, ['team_started_at' => $earlier, 'started_at' => $earlier, 'status' => 'completed', 'ended_at' => $earlier->copy()->addMinutes(30), 'performance_score' => 55]);
        $alice = $this->open($this->alice);

        $this->submitAs($this->alice, $alice)->assertOk();

        $this->assertSame(55.0, (float) $finished->fresh()->performance_score);
    }

    public function test_another_teams_attempts_are_left_alone()
    {
        $dave = User::create(['name' => 'Dave', 'email' => 'dave@example.com', 'password' => bcrypt('password'), 'role' => 'student']);
        $other = Group::create(['lab_id' => $this->laboratory->id, 'name' => 'Team B', 'leader_id' => $dave->id]);
        $other->members()->attach($dave->id);
        $daveSession = $this->open($dave, ['group_id' => $other->id]);
        $alice = $this->open($this->alice);

        $this->submitAs($this->alice, $alice)->assertOk();

        $this->assertSame('in_progress', $daveSession->fresh()->status);
    }

    public function test_a_teammates_check_progress_does_not_hold_up_the_leaders_submit()
    {
        $alice = $this->open($this->alice);
        $bob = $this->open($this->bob);
        $lock = $bob->gradingLock();
        $this->assertTrue($lock->get());

        $this->submitAs($this->alice, $alice)->assertOk();
        $lock->release();

        $this->assertSame('completed', $bob->fresh()->status);
    }

    public function test_the_team_cannot_be_graded_by_two_submits_at_once()
    {
        // On Postgres a taken lock aborts the surrounding transaction, here RefreshDatabase's.
        // Production takes the lock outside any transaction, so locks get their own connection.
        if (DB::getDriverName() === 'pgsql') {
            config([
                'database.connections.test_locks' => config('database.connections.'.config('database.default')),
                'cache.stores.database.lock_connection' => 'test_locks',
            ]);
            Cache::forgetDriver('database');
        }

        $alice = $this->open($this->alice);
        $bob = $this->open($this->bob);
        // The lock a submit by another teammate (the leader until a moment ago) would hold
        $lock = $bob->submitLock();
        $this->assertTrue($lock->get());

        $this->submitAs($this->alice, $alice)->assertStatus(409)->assertJson(['error' => 'grading_in_progress']);
        $lock->release();

        $this->assertSame('in_progress', $alice->fresh()->status);
        $this->assertSame(0, $this->gradeCalls);
    }

    public function test_a_student_without_a_team_on_a_group_lab_submits_alone()
    {
        $dave = User::create(['name' => 'Dave', 'email' => 'dave@example.com', 'password' => bcrypt('password'), 'role' => 'student']);
        $this->laboratory->module->schoolClass->students()->attach($dave->id, ['status' => 'enrolled']);
        $session = $this->open($dave, ['group_id' => null, 'team_started_at' => null]);

        $response = $this->submitAs($dave, $session)->assertOk();

        $this->assertArrayNotHasKey('team', $response->json());
        $this->assertSame('completed', $session->fresh()->status);
    }

    public function test_a_team_whose_leader_was_removed_can_still_submit()
    {
        $this->team->update(['leader_id' => null]);
        $bob = $this->open($this->bob);

        $this->submitAs($this->bob, $bob)->assertOk();

        $this->assertSame($this->bob->id, $this->team->fresh()->leader_id);
    }

    public function test_each_teammates_competency_scores_follow_the_team_grade()
    {
        $competency = Competency::create(['code' => 'JAVA-IO-01', 'name' => 'Console output']);
        $this->laboratory->competencies()->attach($competency->id);
        $alice = $this->open($this->alice);
        $this->open($this->bob);

        $this->submitAs($this->alice, $alice)->assertOk();

        foreach ([$this->alice, $this->bob] as $student) {
            $this->assertEquals(80.0, StudentCompetency::where('competency_id', $competency->id)->where('user_id', $student->id)->value('score_achieved'));
        }
    }

    public function test_the_team_shares_one_clock()
    {
        $this->teamStart = now()->subMinutes(50)->startOfSecond();
        $this->open($this->alice);
        $bob = $this->open($this->bob, ['started_at' => now()->subMinutes(10)]);

        $this->assertEquals($this->teamStart, $bob->clockStartedAt());
        $this->assertFalse($bob->acceptsWork());
    }

    public function test_the_team_gains_no_time_when_its_first_teammate_is_closed()
    {
        $this->teamStart = now()->subMinutes(50)->startOfSecond();
        $alice = $this->open($this->alice, ['last_ping_at' => now()->subMinutes(49)]);
        $bob = $this->open($this->bob, ['started_at' => now()->subMinutes(10), 'last_ping_at' => now()]);

        $alice->update(['status' => 'abandoned', 'ended_at' => now()]);

        $this->assertFalse($bob->fresh()->acceptsWork());
    }

    public function test_a_teammate_who_joins_late_still_has_the_time_the_team_has_left()
    {
        $this->teamStart = now()->subMinutes(20)->startOfSecond();
        $this->open($this->alice);
        $bob = $this->open($this->bob, ['started_at' => now()->subMinutes(1)]);

        $this->assertTrue($bob->acceptsWork());

        $this->actingAs($this->bob)->getJson("/api/v1/sessions/{$bob->id}")
            ->assertOk()
            ->assertJsonPath('time_remaining_seconds', fn ($seconds) => $seconds > 24 * 60 && $seconds <= 25 * 60);
    }

    public function test_opening_the_lab_while_the_team_has_time_left_joins_the_teams_attempt()
    {
        $this->teamStart = now()->subMinutes(20)->startOfSecond();
        $alice = $this->open($this->alice);

        $bob = $this->laboratory->findOrStartAttemptFor($this->bob);

        $this->assertEquals($this->teamStart, $bob->team_started_at);
        $this->assertSame('in_progress', $alice->fresh()->status);
    }

    public function test_opening_the_lab_after_the_teams_time_ran_out_starts_a_new_team_attempt()
    {
        $this->teamStart = now()->subMinutes(50)->startOfSecond();
        $this->open($this->alice);

        $bob = $this->laboratory->findOrStartAttemptFor($this->bob);

        $this->assertTrue($bob->team_started_at->gt($this->teamStart));
        $this->assertTrue($bob->acceptsWork());
    }

    public function test_a_teammate_opening_the_lab_does_not_close_the_leaders_attempt_in_its_last_seconds()
    {
        // Five seconds past the limit: work is still taken for a minute, so a submit isn't lost to lag
        $this->teamStart = now()->subMinutes(45)->subSeconds(5)->startOfSecond();
        $alice = $this->open($this->alice);

        $this->laboratory->findOrStartAttemptFor($this->bob);

        $this->assertSame('in_progress', $alice->fresh()->status);
        $this->assertTrue($alice->fresh()->acceptsWork());
    }

    public function test_a_reopened_earlier_attempt_does_not_run_down_the_teams_clock()
    {
        $yesterday = now()->subDay()->startOfSecond();
        // The instructor reopened Bob's attempt from yesterday; it reopens alone
        $this->open($this->bob, ['team_started_at' => $yesterday, 'started_at' => $yesterday]);
        $alice = $this->open($this->alice);

        $this->assertTrue($alice->acceptsWork());
        $carol = $this->laboratory->findOrStartAttemptFor($this->carol);
        $this->assertEquals($this->teamStart, $carol->team_started_at);
        $this->assertSame('in_progress', $alice->fresh()->status);
    }

    public function test_an_untimed_lab_has_no_team_clock()
    {
        $this->laboratory->update(['time_limit' => 0]);
        $this->teamStart = now()->subMinutes(61)->startOfSecond();
        $this->open($this->alice, ['last_ping_at' => now()]);
        $bob = $this->open($this->bob, ['started_at' => now()->subMinutes(10), 'last_ping_at' => now()]);

        LabSession::autoExpireStaleSessions($this->laboratory->id);

        $this->assertSame('in_progress', $bob->fresh()->status);
    }

    public function test_a_student_moved_into_a_team_mid_attempt_joins_its_attempt()
    {
        $this->open($this->alice);
        $dave = User::create(['name' => 'Dave', 'email' => 'dave@example.com', 'password' => bcrypt('password'), 'role' => 'student']);
        $solo = $this->open($dave, ['group_id' => null, 'team_started_at' => null, 'started_at' => now()->subMinutes(3)]);

        app(LabTeams::class)->addMembers($this->laboratory, $this->team, [$dave->id]);

        $this->assertSame($this->team->id, $solo->fresh()->group_id);
        $this->assertEquals($this->teamStart, $solo->fresh()->team_started_at);
    }

    public function test_a_solo_attempt_keeps_its_own_clock()
    {
        $started = now()->subMinutes(10)->startOfSecond();
        $session = $this->open($this->alice, ['group_id' => null, 'team_started_at' => null, 'started_at' => $started]);

        $this->assertEquals($started, $session->clockStartedAt());
        $this->assertTrue($session->acceptsWork());
    }
}
