<?php

namespace Tests\Feature;

use App\Models\Group;
use App\Models\Laboratory;
use App\Models\LabSession;
use App\Models\Module;
use App\Models\SchoolClass;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The lab monitoring roster: "By team" keeps each team's rows together under a heading,
 * and every row says which of the student's attempts it is.
 */
class MonitoringRosterTeamsTest extends TestCase
{
    use RefreshDatabase;

    protected User $instructor;

    protected User $alice;

    protected User $bob;

    protected User $carol;

    protected SchoolClass $class;

    protected Laboratory $laboratory;

    protected Group $teamA;

    protected Group $teamB;

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

        // Named so that alphabetical order by team and by student disagree
        $this->teamA = Group::create(['lab_id' => $this->laboratory->id, 'name' => 'Team A', 'leader_id' => $this->carol->id]);
        $this->teamA->members()->attach([$this->carol->id]);
        $this->teamB = Group::create(['lab_id' => $this->laboratory->id, 'name' => 'Team B', 'leader_id' => $this->alice->id]);
        $this->teamB->members()->attach([$this->alice->id, $this->bob->id]);
    }

    private function attempt(User $student, Group $team, array $attributes = []): LabSession
    {
        return LabSession::create(array_merge([
            'lab_id' => $this->laboratory->id,
            'user_id' => $student->id,
            'group_id' => $team->id,
            'status' => 'completed',
            'started_at' => now()->subMinutes(30),
            'ended_at' => now()->subMinutes(5),
            'performance_score' => 80,
        ], $attributes));
    }

    private function panel(array $query = [])
    {
        return $this->actingAs($this->instructor)
            ->get(route('classes.telemetry', ['class_id' => $this->class->id, 'lab' => $this->laboratory->id] + $query));
    }

    public function test_each_attempt_is_numbered_per_student()
    {
        $first = $this->attempt($this->alice, $this->teamB, ['status' => 'abandoned']);
        $bob = $this->attempt($this->bob, $this->teamB);
        $second = $this->attempt($this->alice, $this->teamB);

        $data = $this->panel()->assertOk()->viewData('attemptNumbers');

        $this->assertSame(['number' => 1, 'of' => 2], $data[$first->id]);
        $this->assertSame(['number' => 2, 'of' => 2], $data[$second->id]);
        $this->assertSame(['number' => 1, 'of' => 1], $data[$bob->id]);
    }

    public function test_the_roster_shows_the_attempt_number()
    {
        $this->attempt($this->alice, $this->teamB, ['status' => 'abandoned']);
        $this->attempt($this->alice, $this->teamB);
        $this->attempt($this->bob, $this->teamB);

        $this->panel()->assertOk()
            ->assertSee('Attempt 1 of 2')
            ->assertSee('Attempt 2 of 2')
            ->assertSee('Attempt 1');
    }

    public function test_by_team_groups_rows_under_a_heading_per_team()
    {
        $this->attempt($this->alice, $this->teamB);
        $this->attempt($this->carol, $this->teamA);

        $html = $this->panel(['sort' => 'group'])->assertOk()
            ->assertSee('groupByTeam: true', false)
            ->assertSee('data-team-heading="'.$this->teamA->id.'"', false)
            ->assertSee('data-team-heading="'.$this->teamB->id.'"', false)
            ->getContent();

        // The headings carry each team's leader, so the separate team list above the roster goes
        $this->assertStringNotContainsString('aria-label="Teams"', $html);
        $this->assertStringContainsString('Leader: Carol', $html);
        $this->assertStringContainsString('Leader: Alice', $html);
    }

    public function test_by_student_keeps_the_team_list_and_no_headings()
    {
        $this->attempt($this->alice, $this->teamB);

        $this->panel()->assertOk()
            ->assertSee('groupByTeam: false', false)
            ->assertSee('aria-label="Teams"', false)
            ->assertDontSee('data-team-heading=', false);
    }

    public function test_the_roster_index_carries_team_and_attempt_for_client_sorting()
    {
        $alice = $this->attempt($this->alice, $this->teamB);
        $carol = $this->attempt($this->carol, $this->teamA);

        $html = $this->panel(['sort' => 'group'])->assertOk()->getContent();

        $this->assertMatchesRegularExpression('/roster: (\[.*?\]),\n/', $html);
        preg_match('/roster: (\[.*?\]),\n/', $html, $match);
        $roster = collect(json_decode($match[1], true))->keyBy('id');

        $this->assertSame($this->teamB->id, $roster[$alice->id]['team_id']);
        $this->assertSame('team b', $roster[$alice->id]['group']);
        $this->assertSame(1, $roster[$alice->id]['attempt']);
        $this->assertSame($this->teamA->id, $roster[$carol->id]['team_id']);
    }
}
