<?php

namespace Tests\Feature;

use App\Events\ChatMessageSent;
use App\Models\Group;
use App\Models\Laboratory;
use App\Models\LabSessionChat;
use App\Models\Module;
use App\Models\SchoolClass;
use App\Models\User;
use App\Notifications\ClassActivityNotification;
use App\Services\LabTeams;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\Events\NotificationSent;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class LabTeamLeaderTest extends TestCase
{
    use RefreshDatabase;

    protected User $instructor;

    protected User $alice;

    protected User $bob;

    protected User $carol;

    protected User $dave;

    protected Laboratory $laboratory;

    protected function setUp(): void
    {
        parent::setUp();

        $this->instructor = User::create(['name' => 'Prof', 'email' => 'prof@example.com', 'password' => bcrypt('password'), 'role' => 'instructor']);
        $this->alice = User::create(['name' => 'Alice', 'email' => 'alice@example.com', 'password' => bcrypt('password'), 'role' => 'student']);
        $this->bob = User::create(['name' => 'Bob', 'email' => 'bob@example.com', 'password' => bcrypt('password'), 'role' => 'student']);
        $this->carol = User::create(['name' => 'Carol', 'email' => 'carol@example.com', 'password' => bcrypt('password'), 'role' => 'student']);
        $this->dave = User::create(['name' => 'Dave', 'email' => 'dave@example.com', 'password' => bcrypt('password'), 'role' => 'student']);

        $class = SchoolClass::create(['name' => 'CS 101', 'code' => 'CS101', 'instructor_id' => $this->instructor->id]);
        $class->students()->attach([
            $this->alice->id => ['status' => 'enrolled'],
            $this->bob->id => ['status' => 'enrolled'],
            $this->carol->id => ['status' => 'enrolled'],
            $this->dave->id => ['status' => 'enrolled'],
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

    private function team(array $members, ?User $leader = null): Group
    {
        $team = Group::create(['lab_id' => $this->laboratory->id, 'name' => 'Team A', 'leader_id' => $leader?->id]);
        $team->members()->attach(array_map(fn (User $m) => $m->id, $members));

        return $team;
    }

    public function test_the_student_who_starts_a_team_is_its_leader()
    {
        $this->laboratory->update(['team_formation' => 'students']);

        $this->actingAs($this->alice)
            ->post(route('laboratories.my-team.store', $this->laboratory->id), ['members' => [$this->bob->id]])
            ->assertRedirect();

        $this->assertSame($this->alice->id, $this->laboratory->teamOf($this->alice)->leader_id);
    }

    public function test_the_first_teammate_to_open_the_lab_leads_a_team_without_a_leader()
    {
        $team = $this->team([$this->alice, $this->bob, $this->carol]);

        $this->laboratory->findOrStartAttemptFor($this->bob);
        $this->assertSame($this->bob->id, $team->fresh()->leader_id);

        $this->laboratory->findOrStartAttemptFor($this->alice);
        $this->assertSame($this->bob->id, $team->fresh()->leader_id);
    }

    public function test_the_leader_hands_over_directly()
    {
        $team = $this->team([$this->alice, $this->bob, $this->carol], leader: $this->alice);

        $this->assertTrue(app(LabTeams::class)->chooseLeader($team, $this->alice, $this->carol));
        $this->assertSame($this->carol->id, $team->fresh()->leader_id);
    }

    public function test_a_vote_needs_more_than_half_of_the_other_teammates()
    {
        $team = $this->team([$this->alice, $this->bob, $this->carol], leader: $this->alice);
        $teams = app(LabTeams::class);

        $this->assertFalse($teams->chooseLeader($team, $this->bob, $this->bob));
        $this->assertSame($this->alice->id, $team->fresh()->leader_id);
        $this->assertSame([$this->bob->id => $this->bob->id], $teams->leaderVotes($team->fresh()));

        $this->assertTrue($teams->chooseLeader($team, $this->carol, $this->bob));
        $this->assertSame($this->bob->id, $team->fresh()->leader_id);
        $this->assertSame([], $teams->leaderVotes($team->fresh()));
    }

    public function test_two_of_three_other_teammates_carry_a_vote_in_a_team_of_four()
    {
        $team = $this->team([$this->alice, $this->bob, $this->carol, $this->dave], leader: $this->alice);
        $teams = app(LabTeams::class);

        $this->assertFalse($teams->chooseLeader($team, $this->bob, $this->dave));
        $this->assertTrue($teams->chooseLeader($team, $this->carol, $this->dave));
        $this->assertSame($this->dave->id, $team->fresh()->leader_id);
    }

    public function test_changing_a_vote_replaces_the_earlier_one()
    {
        $team = $this->team([$this->alice, $this->bob, $this->carol, $this->dave], leader: $this->alice);
        $teams = app(LabTeams::class);

        $teams->chooseLeader($team, $this->bob, $this->carol);
        $teams->chooseLeader($team, $this->bob, $this->dave);

        $this->assertSame([$this->bob->id => $this->dave->id], $teams->leaderVotes($team->fresh()));
    }

    public function test_in_a_pair_the_other_teammate_can_take_over_alone()
    {
        $team = $this->team([$this->alice, $this->bob], leader: $this->alice);

        $this->assertTrue(app(LabTeams::class)->chooseLeader($team, $this->bob, $this->bob));
        $this->assertSame($this->bob->id, $team->fresh()->leader_id);
    }

    public function test_nobody_outside_the_team_can_be_chosen()
    {
        $team = $this->team([$this->alice, $this->bob], leader: $this->alice);

        try {
            app(LabTeams::class)->chooseLeader($team, $this->alice, $this->carol);
            $this->fail('Choosing a leader from outside the team was allowed.');
        } catch (ValidationException) {
            $this->assertSame($this->alice->id, $team->fresh()->leader_id);
        }
    }

    public function test_nobody_outside_the_team_can_vote()
    {
        $team = $this->team([$this->alice, $this->bob], leader: $this->alice);

        try {
            app(LabTeams::class)->chooseLeader($team, $this->carol, $this->bob);
            $this->fail('A vote from outside the team was allowed.');
        } catch (ValidationException) {
            $this->assertSame($this->alice->id, $team->fresh()->leader_id);
            $this->assertSame([], app(LabTeams::class)->leaderVotes($team->fresh()));
        }
    }

    public function test_removing_the_leader_from_the_team_clears_the_leader()
    {
        $team = $this->team([$this->alice, $this->bob, $this->carol], leader: $this->alice);

        $this->actingAs($this->instructor)
            ->put(route('laboratories.teams.update', [$this->laboratory->id, $team->id]), [
                'name' => $team->name,
                'members' => [$this->bob->id, $this->carol->id],
            ])
            ->assertRedirect();
        $this->assertNull($team->fresh()->leader_id);

        $this->laboratory->findOrStartAttemptFor($this->carol);
        $this->assertSame($this->carol->id, $team->fresh()->leader_id);
    }

    public function test_removing_a_teammate_drops_the_votes_for_and_by_them()
    {
        $team = $this->team([$this->alice, $this->bob, $this->carol, $this->dave], leader: $this->alice);
        $teams = app(LabTeams::class);
        $teams->chooseLeader($team, $this->bob, $this->carol);
        $teams->chooseLeader($team, $this->carol, $this->dave);

        $this->actingAs($this->instructor)
            ->put(route('laboratories.teams.update', [$this->laboratory->id, $team->id]), [
                'name' => $team->name,
                'members' => [$this->alice->id, $this->bob->id, $this->dave->id],
            ])
            ->assertRedirect();

        $this->assertSame([], $teams->leaderVotes($team->fresh()));
    }

    public function test_the_instructor_can_reassign_the_leader_mid_attempt()
    {
        $team = $this->team([$this->alice, $this->bob], leader: $this->alice);
        $this->laboratory->findOrStartAttemptFor($this->alice);

        $this->actingAs($this->instructor)
            ->put(route('laboratories.teams.leader', [$this->laboratory->id, $team->id]), ['leader_id' => $this->bob->id])
            ->assertRedirect(route('laboratories.teams.index', $this->laboratory->id));

        $this->assertSame($this->bob->id, $team->fresh()->leader_id);
    }

    public function test_the_instructor_cannot_pick_a_leader_from_outside_the_team()
    {
        $team = $this->team([$this->alice, $this->bob], leader: $this->alice);

        $this->actingAs($this->instructor)
            ->put(route('laboratories.teams.leader', [$this->laboratory->id, $team->id]), ['leader_id' => $this->carol->id])
            ->assertSessionHasErrors('leader_id');

        $this->assertSame($this->alice->id, $team->fresh()->leader_id);
    }

    public function test_a_student_cannot_use_the_instructor_route()
    {
        $team = $this->team([$this->alice, $this->bob], leader: $this->alice);

        $this->actingAs($this->bob)
            ->put(route('laboratories.teams.leader', [$this->laboratory->id, $team->id]), ['leader_id' => $this->bob->id])
            ->assertForbidden();

        $this->assertSame($this->alice->id, $team->fresh()->leader_id);
    }

    public function test_the_teams_page_shows_each_teams_leader()
    {
        $this->team([$this->alice, $this->bob], leader: $this->bob);

        $this->actingAs($this->instructor)
            ->get(route('laboratories.teams.index', $this->laboratory->id))
            ->assertOk()
            ->assertSee('Leader: Bob');
    }

    public function test_teammates_vote_from_the_lab_page()
    {
        $team = $this->team([$this->alice, $this->bob, $this->carol], leader: $this->alice);

        $this->actingAs($this->carol)
            ->post(route('laboratories.my-team.leader', $this->laboratory->id), ['candidate_id' => $this->bob->id])
            ->assertRedirect(route('laboratories.show', $this->laboratory->id));
        $this->assertSame($this->alice->id, $team->fresh()->leader_id);

        $this->actingAs($this->bob)
            ->post(route('laboratories.my-team.leader', $this->laboratory->id), ['candidate_id' => $this->bob->id])
            ->assertRedirect();
        $this->assertSame($this->bob->id, $team->fresh()->leader_id);
    }

    public function test_a_vote_from_the_lab_page_for_someone_outside_the_team_is_refused()
    {
        $team = $this->team([$this->alice, $this->bob], leader: $this->alice);

        $this->actingAs($this->bob)
            ->post(route('laboratories.my-team.leader', $this->laboratory->id), ['candidate_id' => $this->carol->id])
            ->assertSessionHasErrors('leader');

        $this->assertSame($this->alice->id, $team->fresh()->leader_id);
    }

    public function test_a_student_without_a_team_cannot_vote_from_the_lab_page()
    {
        $this->team([$this->alice, $this->bob], leader: $this->alice);

        $this->actingAs($this->carol)
            ->post(route('laboratories.my-team.leader', $this->laboratory->id), ['candidate_id' => $this->bob->id])
            ->assertForbidden();
    }

    public function test_the_lab_page_shows_the_leader_and_the_votes()
    {
        $team = $this->team([$this->alice, $this->bob, $this->carol], leader: $this->alice);
        app(LabTeams::class)->chooseLeader($team, $this->carol, $this->bob);

        $this->actingAs($this->bob)
            ->get(route('laboratories.show', $this->laboratory->id))
            ->assertOk()
            ->assertSee('Team leader')
            ->assertSee('1 vote')
            ->assertSee('Vote as leader');
    }

    public function test_a_teammate_votes_from_the_extension()
    {
        $team = $this->team([$this->alice, $this->bob], leader: $this->alice);
        $session = $this->laboratory->findOrStartAttemptFor($this->bob);

        $this->actingAs($this->bob)
            ->postJson("/api/v1/sessions/{$session->id}/leader", ['candidate_id' => $this->bob->id])
            ->assertOk()
            ->assertJson(['status' => 'success', 'changed' => true, 'leader_id' => $this->bob->id, 'votes' => []]);

        $this->assertSame($this->bob->id, $team->fresh()->leader_id);
    }

    public function test_a_vote_that_does_not_pass_is_reported_to_the_extension()
    {
        $team = $this->team([$this->alice, $this->bob, $this->carol], leader: $this->alice);
        $session = $this->laboratory->findOrStartAttemptFor($this->bob);

        $this->actingAs($this->bob)
            ->postJson("/api/v1/sessions/{$session->id}/leader", ['candidate_id' => $this->bob->id])
            ->assertOk()
            ->assertJson([
                'changed' => false,
                'leader_id' => $this->alice->id,
                'votes' => [['voter_id' => $this->bob->id, 'candidate_id' => $this->bob->id]],
            ]);
    }

    public function test_the_extension_route_refuses_a_candidate_outside_the_team()
    {
        $team = $this->team([$this->alice, $this->bob], leader: $this->alice);
        $session = $this->laboratory->findOrStartAttemptFor($this->bob);

        $this->actingAs($this->bob)
            ->postJson("/api/v1/sessions/{$session->id}/leader", ['candidate_id' => $this->carol->id])
            ->assertStatus(422);

        $this->assertSame($this->alice->id, $team->fresh()->leader_id);
    }

    public function test_a_student_outside_the_team_cannot_vote_through_someone_elses_session()
    {
        $team = $this->team([$this->alice, $this->bob], leader: $this->alice);
        $session = $this->laboratory->findOrStartAttemptFor($this->bob);

        $this->actingAs($this->carol)
            ->postJson("/api/v1/sessions/{$session->id}/leader", ['candidate_id' => $this->bob->id])
            ->assertForbidden();

        $this->assertSame($this->alice->id, $team->fresh()->leader_id);
    }

    public function test_a_solo_session_has_no_leader_to_choose()
    {
        $session = $this->laboratory->findOrStartAttemptFor($this->alice);

        $this->actingAs($this->alice)
            ->postJson("/api/v1/sessions/{$session->id}/leader", ['candidate_id' => $this->alice->id])
            ->assertStatus(403)
            ->assertJson(['error' => 'no_team']);
    }

    public function test_a_leader_change_notifies_the_team_and_is_posted_in_chat()
    {
        Notification::fake();
        $team = $this->team([$this->alice, $this->bob], leader: $this->alice);
        $this->laboratory->findOrStartAttemptFor($this->alice);

        app(LabTeams::class)->chooseLeader($team, $this->alice, $this->bob);

        Notification::assertSentTo([$this->alice, $this->bob], ClassActivityNotification::class);
        $this->assertSame(1, LabSessionChat::where('message', 'Bob is now the team leader.')->where('is_system', true)->count());
    }

    public function test_a_vote_that_does_not_pass_is_posted_in_chat()
    {
        $team = $this->team([$this->alice, $this->bob, $this->carol], leader: $this->alice);
        $this->laboratory->findOrStartAttemptFor($this->bob);

        app(LabTeams::class)->chooseLeader($team, $this->carol, $this->bob);

        $this->assertSame(1, LabSessionChat::where('message', 'Carol voted for Bob as team leader.')->count());
    }

    public function test_the_session_payload_names_the_leader()
    {
        $team = $this->team([$this->alice, $this->bob, $this->carol], leader: $this->alice);
        $this->laboratory->findOrStartAttemptFor($this->alice);
        $session = $this->laboratory->findOrStartAttemptFor($this->bob);
        app(LabTeams::class)->chooseLeader($team, $this->carol, $this->bob);

        $json = $this->actingAs($this->bob)->getJson("/api/v1/sessions/{$session->id}")->assertOk()->json();

        $this->assertSame([
            'id' => $team->id,
            'name' => 'Team A',
            'leader_id' => $this->alice->id,
            'leader_name' => 'Alice',
            'is_leader' => false,
            'can_submit' => false,
            'votes' => [['voter_id' => $this->carol->id, 'candidate_id' => $this->bob->id]],
        ], $json['team']);

        $teammates = collect($json['teammates'])->keyBy('id');
        $this->assertTrue($teammates[$this->alice->id]['is_leader']);
        $this->assertFalse($teammates[$this->bob->id]['is_leader']);
        $this->assertTrue($teammates[$this->alice->id]['in_lab']);
        $this->assertFalse($teammates[$this->carol->id]['in_lab']);
    }

    public function test_the_leader_is_told_they_can_submit()
    {
        $this->team([$this->alice, $this->bob], leader: $this->alice);
        $session = $this->laboratory->findOrStartAttemptFor($this->alice);

        $this->actingAs($this->alice)->getJson("/api/v1/sessions/{$session->id}")
            ->assertOk()
            ->assertJsonPath('team.is_leader', true)
            ->assertJsonPath('team.can_submit', true);
    }

    public function test_reading_the_session_gives_a_leaderless_team_a_leader()
    {
        $team = $this->team([$this->alice, $this->bob], leader: $this->alice);
        $session = $this->laboratory->findOrStartAttemptFor($this->bob);
        $team->update(['leader_id' => null]);

        $this->actingAs($this->bob)->getJson("/api/v1/sessions/{$session->id}")
            ->assertOk()
            ->assertJsonPath('team.leader_id', $this->bob->id);
    }

    public function test_a_session_without_a_team_has_no_team_in_its_payload()
    {
        $session = $this->laboratory->findOrStartAttemptFor($this->alice);

        $json = $this->actingAs($this->alice)->getJson("/api/v1/sessions/{$session->id}")->assertOk()->json();

        $this->assertArrayHasKey('team', $json);
        $this->assertNull($json['team']);
    }

    public function test_reading_who_leads_takes_no_lock_once_the_team_has_a_leader()
    {
        // Every session read asks who leads, for every student, every few seconds. Locking
        // the lab row each time would queue the whole class on one row.
        $team = $this->team([$this->alice, $this->bob], leader: $this->alice);
        $outside = DB::transactionLevel();
        $deepest = $outside;
        DB::listen(function () use (&$deepest) {
            $deepest = max($deepest, DB::transactionLevel());
        });

        $leader = app(LabTeams::class)->ensureLeader($team->fresh(), $this->bob);

        $this->assertSame($this->alice->id, $leader->id);
        $this->assertSame($outside, $deepest);
    }

    public function test_a_leader_change_is_announced_after_the_team_lock_is_released()
    {
        // A slow broadcast or email must not hold the lock, and a failed one must not undo the change
        $team = $this->team([$this->alice, $this->bob, $this->carol], leader: $this->alice);
        $this->laboratory->findOrStartAttemptFor($this->bob);
        $teams = app(LabTeams::class);
        $teams->chooseLeader($team, $this->bob, $this->bob);

        $outside = DB::transactionLevel();
        $levels = [];
        Event::listen(ChatMessageSent::class, function () use (&$levels) {
            $levels[] = DB::transactionLevel();
        });
        Event::listen(NotificationSent::class, function () use (&$levels) {
            $levels[] = DB::transactionLevel();
        });

        $this->assertTrue($teams->chooseLeader($team, $this->carol, $this->bob));

        $this->assertNotEmpty($levels);
        $this->assertSame([$outside], array_values(array_unique($levels)));
    }

    public function test_a_leader_who_leaves_a_student_made_team_stops_leading_it()
    {
        $this->laboratory->update(['team_formation' => 'students']);
        $team = $this->team([$this->alice, $this->bob], leader: $this->alice);

        $this->actingAs($this->alice)
            ->delete(route('laboratories.my-team.destroy', $this->laboratory->id))
            ->assertRedirect();

        $this->assertNull($team->fresh()->leader_id);
    }
}
