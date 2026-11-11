<?php

namespace Tests\Feature;

use App\Models\Group;
use App\Models\Laboratory;
use App\Models\LabSession;
use App\Models\Module;
use App\Models\SchoolClass;
use App\Models\User;
use App\Notifications\ClassActivityNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
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

    public function test_the_teams_page_counts_only_students_without_a_team()
    {
        $team = Group::create(['lab_id' => $this->laboratory->id, 'name' => 'Team A']);
        $team->members()->attach([$this->bob->id, $this->carol->id]);

        $this->actingAs($this->instructor)
            ->get(route('laboratories.teams.index', $this->laboratory->id))
            ->assertOk()
            ->assertViewHas('unassigned', fn ($unassigned) => $unassigned->pluck('id')->all() === [$this->alice->id])
            ->assertSee('1 of 3 students without a team');
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

    private function labFields(array $overrides = []): array
    {
        return array_merge([
            'title' => 'New Lab',
            'description' => 'Description',
            'time_limit' => 45,
            'module_id' => $this->laboratory->module_id,
        ], $overrides);
    }

    private function enrollMore(int $count): void
    {
        $class = $this->laboratory->module->schoolClass;
        foreach (range(1, $count) as $n) {
            $student = User::create(['name' => "Student {$n}", 'email' => "student{$n}@example.com", 'password' => bcrypt('password'), 'role' => 'student']);
            $class->students()->attach($student->id, ['status' => 'enrolled']);
        }
    }

    private function teamSizes(): array
    {
        return $this->laboratory->groups()->withCount('members')->get()->pluck('members_count')->sort()->values()->all();
    }

    private function letStudentsPick(int $size = 2): void
    {
        $this->laboratory->update(['team_size' => $size, 'team_formation' => 'students']);
    }

    public function test_a_group_lab_needs_a_team_size_and_formation()
    {
        $this->actingAs($this->instructor)
            ->post(route('laboratories.store'), $this->labFields(['is_group_lab' => '1']))
            ->assertSessionHasErrors(['team_size', 'team_formation']);

        $this->actingAs($this->instructor)
            ->post(route('laboratories.store'), $this->labFields(['is_group_lab' => '1', 'team_size' => 1, 'team_formation' => 'students']))
            ->assertSessionHasErrors('team_size');

        $this->actingAs($this->instructor)
            ->post(route('laboratories.store'), $this->labFields(['is_group_lab' => '1', 'team_size' => 4, 'team_formation' => 'students']))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('laboratories', ['title' => 'New Lab', 'team_size' => 4, 'team_formation' => 'students']);
    }

    public function test_a_solo_lab_ignores_the_hidden_team_fields()
    {
        $this->actingAs($this->instructor)
            ->post(route('laboratories.store'), $this->labFields(['team_size' => 1, 'team_formation' => 'random']))
            ->assertSessionHasNoErrors();

        $lab = Laboratory::where('title', 'New Lab')->firstOrFail();
        $this->assertNull($lab->team_size);
        $this->assertSame('instructor', $lab->team_formation);
        $this->assertSame(0, $lab->groups()->count());
    }

    public function test_a_random_lab_draws_even_teams_when_created()
    {
        $this->enrollMore(7);

        $this->actingAs($this->instructor)
            ->post(route('laboratories.store'), $this->labFields(['is_group_lab' => '1', 'team_size' => 3, 'team_formation' => 'random']))
            ->assertSessionHasNoErrors();

        $lab = Laboratory::where('title', 'New Lab')->firstOrFail();
        $sizes = $lab->groups()->withCount('members')->get()->pluck('members_count')->sort()->values()->all();

        $this->assertSame([2, 2, 3, 3], $sizes);
    }

    public function test_switching_a_lab_to_random_keeps_teams_that_already_exist()
    {
        $team = Group::create(['lab_id' => $this->laboratory->id, 'name' => 'Team A']);
        $team->members()->attach($this->alice->id);

        $this->actingAs($this->instructor)
            ->put(route('laboratories.update', $this->laboratory->id), $this->labFields(['is_group_lab' => '1', 'team_size' => 2, 'team_formation' => 'random']))
            ->assertSessionHasNoErrors();

        $this->assertSame([1], $this->teamSizes());
    }

    public function test_randomize_fills_open_seats_before_making_new_teams()
    {
        $this->laboratory->update(['team_size' => 2, 'team_formation' => 'random']);
        $this->enrollMore(1);
        $team = Group::create(['lab_id' => $this->laboratory->id, 'name' => 'Team 1']);
        $team->members()->attach($this->alice->id);
        $open = LabSession::create(['lab_id' => $this->laboratory->id, 'user_id' => $this->bob->id, 'status' => 'in_progress', 'started_at' => now()]);

        $this->actingAs($this->instructor)
            ->post(route('laboratories.teams.randomize', $this->laboratory->id))
            ->assertRedirect(route('laboratories.teams.index', $this->laboratory->id));

        $this->assertSame([2, 2], $this->teamSizes());
        $this->assertSame($this->laboratory->teamOf($this->bob)->id, $open->fresh()->group_id);
    }

    public function test_randomize_needs_a_team_size()
    {
        $team = Group::create(['lab_id' => $this->laboratory->id, 'name' => 'Team A']);
        $team->members()->attach($this->alice->id);

        $this->actingAs($this->instructor)
            ->post(route('laboratories.teams.randomize', $this->laboratory->id), ['reshuffle' => 1])
            ->assertSessionHas('error');

        $this->assertDatabaseHas('groups', ['id' => $team->id]);
    }

    public function test_draw_all_again_replaces_teams_until_one_has_an_attempt()
    {
        $this->laboratory->update(['team_size' => 3, 'team_formation' => 'random']);
        $team = Group::create(['lab_id' => $this->laboratory->id, 'name' => 'Team A']);
        $team->members()->attach($this->alice->id);

        $this->actingAs($this->instructor)
            ->post(route('laboratories.teams.randomize', $this->laboratory->id), ['reshuffle' => 1])
            ->assertSessionHas('success');

        $this->assertDatabaseMissing('groups', ['id' => $team->id]);
        $this->assertSame([3], $this->teamSizes());

        $drawn = $this->laboratory->groups()->firstOrFail();
        LabSession::create(['lab_id' => $this->laboratory->id, 'user_id' => $this->alice->id, 'group_id' => $drawn->id, 'status' => 'completed', 'started_at' => now()]);

        $this->actingAs($this->instructor)
            ->post(route('laboratories.teams.randomize', $this->laboratory->id), ['reshuffle' => 1])
            ->assertSessionHas('error');

        $this->assertDatabaseHas('groups', ['id' => $drawn->id]);
    }

    public function test_only_the_class_instructor_can_randomize()
    {
        $this->laboratory->update(['team_size' => 2]);

        $this->actingAs($this->alice)->post(route('laboratories.teams.randomize', $this->laboratory->id))->assertForbidden();

        $this->assertDatabaseCount('groups', 0);
    }

    public function test_a_student_picks_teammates_and_they_are_told()
    {
        Notification::fake();
        $this->letStudentsPick();

        $this->actingAs($this->alice)
            ->post(route('laboratories.my-team.store', $this->laboratory->id), ['members' => [$this->bob->id]])
            ->assertRedirect(route('laboratories.show', $this->laboratory->id));

        $team = $this->laboratory->teamOf($this->alice);
        $this->assertSame('Team 1', $team->name);
        $this->assertSame($team->id, $this->laboratory->teamOf($this->bob)->id);
        Notification::assertSentTo($this->bob, ClassActivityNotification::class);
        Notification::assertNotSentTo($this->alice, ClassActivityNotification::class);
        $this->assertSame($team->id, $this->laboratory->findOrStartAttemptFor($this->bob)->group_id);
    }

    public function test_a_team_cannot_pass_the_team_size()
    {
        $this->letStudentsPick();

        $this->actingAs($this->alice)
            ->post(route('laboratories.my-team.store', $this->laboratory->id), ['members' => [$this->bob->id, $this->carol->id]])
            ->assertSessionHasErrors('members');

        $this->assertDatabaseCount('groups', 0);
    }

    public function test_a_student_cannot_pick_someone_who_is_not_free()
    {
        $this->letStudentsPick(3);
        $outsider = User::create(['name' => 'Outsider', 'email' => 'out@example.com', 'password' => bcrypt('password'), 'role' => 'student']);
        $team = Group::create(['lab_id' => $this->laboratory->id, 'name' => 'Team A']);
        $team->members()->attach($this->bob->id);

        foreach ([$this->bob->id, $outsider->id, $this->alice->id] as $picked) {
            $this->actingAs($this->alice)
                ->post(route('laboratories.my-team.store', $this->laboratory->id), ['members' => [$picked]])
                ->assertSessionHasErrors('members');
        }

        $this->assertNull($this->laboratory->teamOf($this->alice));
    }

    public function test_students_can_pick_only_on_labs_set_to_students_pick()
    {
        $this->laboratory->update(['team_size' => 2, 'team_formation' => 'random']);

        $this->actingAs($this->alice)
            ->post(route('laboratories.my-team.store', $this->laboratory->id), ['members' => [$this->bob->id]])
            ->assertForbidden();
    }

    public function test_only_enrolled_students_can_pick()
    {
        $this->letStudentsPick();
        $outsider = User::create(['name' => 'Outsider', 'email' => 'out@example.com', 'password' => bcrypt('password'), 'role' => 'student']);

        $this->actingAs($outsider)
            ->post(route('laboratories.my-team.store', $this->laboratory->id), ['members' => [$this->bob->id]])
            ->assertForbidden();
        $this->actingAs($this->instructor)
            ->post(route('laboratories.my-team.store', $this->laboratory->id), ['members' => [$this->bob->id]])
            ->assertForbidden();
    }

    public function test_a_team_locks_once_a_member_starts_the_lab()
    {
        $this->letStudentsPick(3);
        $team = Group::create(['lab_id' => $this->laboratory->id, 'name' => 'Team A']);
        $team->members()->attach([$this->alice->id, $this->bob->id]);
        LabSession::create(['lab_id' => $this->laboratory->id, 'user_id' => $this->bob->id, 'group_id' => $team->id, 'status' => 'in_progress', 'started_at' => now()]);

        $this->actingAs($this->alice)
            ->post(route('laboratories.my-team.store', $this->laboratory->id), ['members' => [$this->carol->id]])
            ->assertSessionHasErrors('members');
        $this->actingAs($this->alice)
            ->delete(route('laboratories.my-team.destroy', $this->laboratory->id))
            ->assertSessionHasErrors('members');

        $this->assertSame(2, $team->members()->count());
    }

    public function test_a_student_who_already_started_cannot_be_picked()
    {
        $this->letStudentsPick();
        LabSession::create(['lab_id' => $this->laboratory->id, 'user_id' => $this->bob->id, 'status' => 'completed', 'started_at' => now()]);

        $this->actingAs($this->alice)
            ->post(route('laboratories.my-team.store', $this->laboratory->id), ['members' => [$this->bob->id]])
            ->assertSessionHasErrors('members');

        $this->assertDatabaseCount('groups', 0);
    }

    public function test_leaving_removes_the_student_and_an_empty_team()
    {
        $this->letStudentsPick();
        $team = Group::create(['lab_id' => $this->laboratory->id, 'name' => 'Team A']);
        $team->members()->attach([$this->alice->id, $this->bob->id]);

        $this->actingAs($this->alice)->delete(route('laboratories.my-team.destroy', $this->laboratory->id))->assertRedirect();
        $this->assertNull($this->laboratory->teamOf($this->alice));
        $this->assertDatabaseHas('groups', ['id' => $team->id]);

        $this->actingAs($this->bob)->delete(route('laboratories.my-team.destroy', $this->laboratory->id))->assertRedirect();
        $this->assertDatabaseMissing('groups', ['id' => $team->id]);
    }

    public function test_the_lab_page_lists_free_classmates_without_their_emails()
    {
        $this->letStudentsPick();
        $team = Group::create(['lab_id' => $this->laboratory->id, 'name' => 'Team A']);
        $team->members()->attach($this->carol->id);

        $this->actingAs($this->alice)
            ->get(route('laboratories.show', $this->laboratory->id))
            ->assertOk()
            ->assertSee('Pick your teammates')
            ->assertSee('Bob')
            ->assertDontSee('bob@example.com')
            ->assertDontSee('Carol');
    }

    public function test_the_picker_stays_off_other_labs()
    {
        $this->laboratory->update(['team_size' => 2, 'team_formation' => 'random']);

        $this->actingAs($this->alice)
            ->get(route('laboratories.show', $this->laboratory->id))
            ->assertOk()
            ->assertDontSee('Pick your teammates');
    }

    public function test_the_instructor_may_go_over_the_team_size()
    {
        $this->laboratory->update(['team_size' => 2, 'team_formation' => 'students']);

        $this->actingAs($this->instructor)
            ->post(route('laboratories.teams.store', $this->laboratory->id), ['name' => 'Team A', 'members' => [$this->alice->id, $this->bob->id, $this->carol->id]])
            ->assertSessionHasNoErrors();

        $this->assertSame([3], $this->teamSizes());
    }
}
