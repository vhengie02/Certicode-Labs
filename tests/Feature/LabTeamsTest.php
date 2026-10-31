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

class LabTeamsTest extends TestCase
{
    use RefreshDatabase;

    protected User $instructor;

    protected User $alice;

    protected User $bob;

    protected User $carol;

    protected Laboratory $laboratory;

    protected function setUp(): void
    {
        parent::setUp();

        $this->instructor = User::create(['name' => 'Prof', 'email' => 'prof@example.com', 'password' => bcrypt('password'), 'role' => 'instructor']);
        $this->alice = User::create(['name' => 'Alice', 'email' => 'alice@example.com', 'password' => bcrypt('password'), 'role' => 'student']);
        $this->bob = User::create(['name' => 'Bob', 'email' => 'bob@example.com', 'password' => bcrypt('password'), 'role' => 'student']);
        $this->carol = User::create(['name' => 'Carol', 'email' => 'carol@example.com', 'password' => bcrypt('password'), 'role' => 'student']);

        $class = SchoolClass::create(['name' => 'CS 101', 'code' => 'CS101', 'instructor_id' => $this->instructor->id]);
        $class->students()->attach([
            $this->alice->id => ['status' => 'enrolled'],
            $this->bob->id => ['status' => 'enrolled'],
            $this->carol->id => ['status' => 'enrolled'],
        ]);
        $module = Module::create(['class_id' => $class->id, 'title' => 'Module 1', 'content' => 'Content', 'order_index' => 0]);
        $this->laboratory = Laboratory::create([
            'module_id' => $module->id,
            'title' => 'Team Lab',
            'description' => 'Description',
            'time_limit' => 45,
            'is_group_lab' => true,
            'tasks_definition' => [['id' => 1, 'task' => 'Task']],
        ]);
    }

    public function test_instructor_sees_the_teams_page_with_enrolled_students()
    {
        $this->actingAs($this->instructor)
            ->get(route('laboratories.teams.index', $this->laboratory->id))
            ->assertOk()
            ->assertSee('New team')
            ->assertSee('Alice')
            ->assertSee('Carol');
    }

    public function test_only_the_class_instructor_can_manage_teams()
    {
        $other = User::create(['name' => 'Other', 'email' => 'other@example.com', 'password' => bcrypt('password'), 'role' => 'instructor']);

        $this->actingAs($other)->get(route('laboratories.teams.index', $this->laboratory->id))->assertForbidden();
        $this->actingAs($this->alice)
            ->post(route('laboratories.teams.store', $this->laboratory->id), ['name' => 'Mine', 'members' => [$this->alice->id]])
            ->assertForbidden();

        $this->assertDatabaseCount('groups', 0);
    }

    public function test_instructor_creates_a_team_and_members_attempts_join_it()
    {
        $this->actingAs($this->instructor)
            ->post(route('laboratories.teams.store', $this->laboratory->id), ['name' => 'Team A', 'members' => [$this->alice->id, $this->bob->id]])
            ->assertRedirect(route('laboratories.teams.index', $this->laboratory->id));

        $team = Group::where('lab_id', $this->laboratory->id)->firstOrFail();
        $this->assertEqualsCanonicalizing([$this->alice->id, $this->bob->id], $team->members()->pluck('users.id')->all());

        $this->assertSame($team->id, $this->laboratory->findOrStartAttemptFor($this->alice)->group_id);
        $this->assertNull($this->laboratory->findOrStartAttemptFor($this->carol)->group_id);
    }

    public function test_solo_labs_ignore_teams()
    {
        $team = Group::create(['lab_id' => $this->laboratory->id, 'name' => 'Team A']);
        $team->members()->attach($this->alice->id);
        $this->laboratory->update(['is_group_lab' => false]);

        $this->assertNull($this->laboratory->findOrStartAttemptFor($this->alice)->group_id);
    }

    public function test_a_student_can_be_in_only_one_team_per_lab()
    {
        $team = Group::create(['lab_id' => $this->laboratory->id, 'name' => 'Team A']);
        $team->members()->attach($this->alice->id);

        $this->actingAs($this->instructor)
            ->post(route('laboratories.teams.store', $this->laboratory->id), ['name' => 'Team B', 'members' => [$this->alice->id, $this->bob->id]])
            ->assertSessionHasErrors('members');

        $this->assertDatabaseMissing('groups', ['name' => 'Team B']);
    }

    public function test_students_outside_the_class_cannot_join()
    {
        $outsider = User::create(['name' => 'Outsider', 'email' => 'out@example.com', 'password' => bcrypt('password'), 'role' => 'student']);

        $this->actingAs($this->instructor)
            ->post(route('laboratories.teams.store', $this->laboratory->id), ['name' => 'Team A', 'members' => [$outsider->id]])
            ->assertSessionHasErrors('members.0');
    }

    public function test_changing_members_moves_open_attempts_but_not_finished_ones()
    {
        $team = Group::create(['lab_id' => $this->laboratory->id, 'name' => 'Team A']);
        $team->members()->attach([$this->alice->id, $this->bob->id]);
        $bobDone = LabSession::create(['lab_id' => $this->laboratory->id, 'user_id' => $this->bob->id, 'group_id' => $team->id, 'status' => 'completed', 'started_at' => now()]);
        $bobOpen = LabSession::create(['lab_id' => $this->laboratory->id, 'user_id' => $this->bob->id, 'group_id' => $team->id, 'status' => 'in_progress', 'started_at' => now()]);
        $carolOpen = LabSession::create(['lab_id' => $this->laboratory->id, 'user_id' => $this->carol->id, 'status' => 'in_progress', 'started_at' => now()]);

        $this->actingAs($this->instructor)
            ->put(route('laboratories.teams.update', [$this->laboratory->id, $team->id]), ['name' => 'Team Alpha', 'members' => [$this->alice->id, $this->carol->id]])
            ->assertRedirect();

        $this->assertSame('Team Alpha', $team->fresh()->name);
        $this->assertNull($bobOpen->fresh()->group_id);
        $this->assertSame($team->id, $bobDone->fresh()->group_id);
        $this->assertSame($team->id, $carolOpen->fresh()->group_id);
    }

    public function test_deleting_a_team_keeps_its_attempts()
    {
        $team = Group::create(['lab_id' => $this->laboratory->id, 'name' => 'Team A']);
        $team->members()->attach($this->alice->id);
        $session = LabSession::create(['lab_id' => $this->laboratory->id, 'user_id' => $this->alice->id, 'group_id' => $team->id, 'status' => 'in_progress', 'started_at' => now()]);

        $this->actingAs($this->instructor)
            ->delete(route('laboratories.teams.destroy', [$this->laboratory->id, $team->id]))
            ->assertRedirect();

        $this->assertDatabaseMissing('groups', ['id' => $team->id]);
        $this->assertNull($session->fresh()->group_id);
    }

    public function test_student_sees_their_team_on_the_lab_page()
    {
        $team = Group::create(['lab_id' => $this->laboratory->id, 'name' => 'Team A']);
        $team->members()->attach([$this->alice->id, $this->bob->id]);

        $this->actingAs($this->alice)
            ->get(route('laboratories.show', $this->laboratory->id))
            ->assertOk()
            ->assertSee('Team A · 2 members');
    }
}
