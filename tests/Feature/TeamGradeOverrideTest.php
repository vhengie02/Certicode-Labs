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
use Tests\TestCase;

/**
 * A team shares its grade, so the instructor's change to it reaches the whole team unless
 * they say otherwise. Teammates who never opened the lab are listed as not taking part.
 */
class TeamGradeOverrideTest extends TestCase
{
    use RefreshDatabase;

    protected User $instructor;

    protected User $alice;

    protected User $bob;

    protected User $carol;

    protected SchoolClass $class;

    protected Laboratory $laboratory;

    protected Group $team;

    protected function setUp(): void
    {
        parent::setUp();

        $this->instructor = User::create(['name' => 'Prof', 'email' => 'prof@example.com', 'password' => bcrypt('password'), 'role' => 'instructor']);
        $this->alice = User::create(['name' => 'Alice', 'email' => 'alice@example.com', 'password' => bcrypt('password'), 'role' => 'student']);
        $this->bob = User::create(['name' => 'Bob', 'email' => 'bob@example.com', 'password' => bcrypt('password'), 'role' => 'student']);
        $this->carol = User::create(['name' => 'Carol', 'email' => 'carol@example.com', 'password' => bcrypt('password'), 'role' => 'student']);

        $this->class = SchoolClass::create(['name' => 'Java 101', 'code' => 'JAVA-101', 'instructor_id' => $this->instructor->id]);
        foreach ([$this->alice, $this->bob, $this->carol] as $student) {
            $this->class->students()->attach($student->id, ['status' => 'enrolled']);
        }
        $module = Module::create(['class_id' => $this->class->id, 'title' => 'Module', 'content' => 'Content']);
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
    }

    private function graded(User $student, array $attributes = []): LabSession
    {
        return LabSession::create(array_merge([
            'lab_id' => $this->laboratory->id,
            'user_id' => $student->id,
            'group_id' => $this->team->id,
            'status' => 'completed',
            'started_at' => now()->subMinutes(30),
            'ended_at' => now()->subMinutes(5)->startOfSecond(),
            'performance_score' => 80,
        ], $attributes));
    }

    private function override(LabSession $session, array $extra = [])
    {
        return $this->actingAs($this->instructor)->postJson("/instructor/sessions/{$session->id}/override-grade", array_merge([
            'override_score' => 90,
            'override_reason' => 'Clean solution',
        ], $extra));
    }

    public function test_an_override_applies_to_the_whole_team_by_default()
    {
        $alice = $this->graded($this->alice);
        $bob = $this->graded($this->bob);

        $this->override($alice)->assertOk()->assertJsonPath('team_sessions_changed', 2);

        $this->assertSame(90.0, $alice->fresh()->effective_score);
        $this->assertSame(90.0, $bob->fresh()->effective_score);
        $this->assertSame('Clean solution', $bob->fresh()->instructor_override_reason);
        $this->assertSame(1, TelemetryLog::where('lab_session_id', $bob->id)->where('event_type', 'instructor_grade_override')->count());
    }

    public function test_an_override_can_target_one_student()
    {
        $alice = $this->graded($this->alice);
        $bob = $this->graded($this->bob);

        $this->override($alice, ['apply_to_team' => false])->assertOk()->assertJsonPath('team_sessions_changed', 1);

        $this->assertSame(90.0, $alice->fresh()->effective_score);
        $this->assertSame(80.0, $bob->fresh()->effective_score);
    }

    public function test_a_team_override_leaves_an_earlier_team_attempt_alone()
    {
        $earlier = $this->graded($this->bob, ['started_at' => now()->subDay(), 'ended_at' => now()->subDay()->addMinutes(20), 'performance_score' => 40]);
        $alice = $this->graded($this->alice);

        $this->override($alice)->assertOk();

        $this->assertSame(40.0, $earlier->fresh()->effective_score);
    }

    public function test_a_team_override_reaches_a_teammate_who_stopped_working_earlier()
    {
        // Closed for inactivity before the submit, so their attempt ended at another time
        $teamStart = now()->subMinutes(30)->startOfSecond();
        $alice = $this->graded($this->alice, ['team_started_at' => $teamStart]);
        $bob = $this->graded($this->bob, ['team_started_at' => $teamStart, 'ended_at' => now()->subMinutes(15)->startOfSecond()]);
        $earlier = $this->graded($this->carol, ['team_started_at' => now()->subDay()->startOfSecond(), 'performance_score' => 40]);

        $this->override($alice)->assertOk()->assertJsonPath('team_sessions_changed', 2);

        $this->assertSame(90.0, $bob->fresh()->effective_score);
        $this->assertSame(40.0, $earlier->fresh()->effective_score);
    }

    public function test_an_override_on_a_solo_attempt_touches_only_it()
    {
        $solo = $this->graded($this->alice, ['group_id' => null]);
        $other = $this->graded($this->bob, ['group_id' => null]);

        $this->override($solo)->assertOk()->assertJsonPath('team_sessions_changed', 1);

        $this->assertSame(90.0, $solo->fresh()->effective_score);
        $this->assertSame(80.0, $other->fresh()->effective_score);
    }

    public function test_monitoring_lists_teammates_who_did_not_take_part()
    {
        $this->graded($this->alice);
        $this->graded($this->bob);

        $this->actingAs($this->instructor)
            ->get(route('classes.telemetry', ['class_id' => $this->class->id, 'lab' => $this->laboratory->id]))
            ->assertOk()
            ->assertSee('Did not take part')
            ->assertSee('Carol')
            ->assertSee('Leader: Alice');

        $this->actingAs($this->instructor)
            ->getJson(route('instructor.monitoring.data', $this->laboratory->id))
            ->assertOk()
            ->assertJsonPath('teams.0.leader_id', $this->alice->id)
            ->assertJsonPath('teams.0.absent', [['id' => $this->carol->id, 'name' => 'Carol']]);
    }

    public function test_a_team_where_everyone_took_part_lists_nobody()
    {
        foreach ([$this->alice, $this->bob, $this->carol] as $student) {
            $this->graded($student);
        }

        $this->actingAs($this->instructor)
            ->get(route('classes.telemetry', ['class_id' => $this->class->id, 'lab' => $this->laboratory->id]))
            ->assertOk()
            ->assertDontSee('Did not take part');
    }
}
