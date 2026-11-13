<?php

namespace Tests\Feature;

use App\Models\Group;
use App\Models\Laboratory;
use App\Models\LabSession;
use App\Models\Module;
use App\Models\SchoolClass;
use App\Models\TeamDoc;
use App\Models\TeamDocUpdate;
use App\Models\User;
use App\Services\LabTeams;
use App\Services\LlmEvaluationService;
use App\Services\SandboxExecutionService;
use Carbon\CarbonInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Shared typing: each team attempt has one document. The server hands its channel name and
 * key to the team's members only, and keeps the edits so a late joiner can load them. It
 * never reads an edit.
 */
class TeamDocTest extends TestCase
{
    use RefreshDatabase;

    protected User $alice;

    protected User $bob;

    protected User $carol;

    protected User $dave;

    protected Laboratory $laboratory;

    protected Group $team;

    protected CarbonInterface $teamStart;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.team_sync.supabase_url' => 'https://example.supabase.co',
            'services.team_sync.publishable_key' => 'sb_publishable_test',
        ]);

        $instructor = User::create(['name' => 'Prof', 'email' => 'prof@example.com', 'password' => bcrypt('password'), 'role' => 'instructor']);
        $class = SchoolClass::create(['name' => 'Java 101', 'code' => 'JAVA-101', 'instructor_id' => $instructor->id]);
        foreach (['alice', 'bob', 'carol', 'dave'] as $name) {
            $this->{$name} = User::create(['name' => ucfirst($name), 'email' => "{$name}@example.com", 'password' => bcrypt('password'), 'role' => 'student']);
            $class->students()->attach($this->{$name}->id, ['status' => 'enrolled']);
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
    }

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

    private function secrets(User $student, LabSession $session)
    {
        return $this->actingAs($student)->getJson("/api/v1/sessions/{$session->id}/team-doc");
    }

    private function append(User $student, LabSession $session, array $body)
    {
        return $this->actingAs($student)->postJson("/api/v1/sessions/{$session->id}/team-doc/updates", $body);
    }

    private function updates(User $student, LabSession $session, int $after = 0)
    {
        return $this->actingAs($student)->getJson("/api/v1/sessions/{$session->id}/team-doc/updates?after={$after}");
    }

    public function test_teammates_get_the_same_channel_and_key_for_one_team_attempt()
    {
        $alice = $this->secrets($this->alice, $this->open($this->alice))->assertOk()->json();
        $bob = $this->secrets($this->bob, $this->open($this->bob))->assertOk()->json();

        $this->assertTrue($alice['enabled']);
        $this->assertMatchesRegularExpression('/^td-[0-9a-f]{48}$/', $alice['topic']);
        $this->assertSame(32, strlen(base64_decode($alice['key'], true)));
        $this->assertSame($alice['topic'], $bob['topic']);
        $this->assertSame($alice['key'], $bob['key']);
        $this->assertSame('https://example.supabase.co', $alice['supabase_url']);
        $this->assertSame('sb_publishable_test', $alice['publishable_key']);
        $this->assertSame(400, $alice['min_send_interval_ms']);
        $this->assertSame(0, $alice['last_update_id']);
        $this->assertSame(1, TeamDoc::count());
    }

    public function test_another_team_gets_a_different_channel_and_key()
    {
        $other = Group::create(['lab_id' => $this->laboratory->id, 'name' => 'Team B', 'leader_id' => $this->dave->id]);
        $other->members()->attach($this->dave->id);

        $ours = $this->secrets($this->alice, $this->open($this->alice))->json();
        $theirs = $this->secrets($this->dave, $this->open($this->dave, ['group_id' => $other->id]))->json();

        $this->assertNotSame($ours['topic'], $theirs['topic']);
        $this->assertNotSame($ours['key'], $theirs['key']);
    }

    public function test_a_new_team_attempt_gets_a_new_channel_and_key()
    {
        $this->laboratory->update(['max_attempts' => 3]);
        $first = $this->open($this->alice);
        $before = $this->secrets($this->alice, $first)->json();
        $first->update(['status' => 'abandoned', 'ended_at' => now()]);

        $again = $this->open($this->alice, ['team_started_at' => now()->startOfSecond(), 'started_at' => now()]);
        $after = $this->secrets($this->alice, $again)->json();

        $this->assertNotSame($before['topic'], $after['topic']);
        $this->assertNotSame($before['key'], $after['key']);
    }

    public function test_the_key_is_not_stored_in_plain_text()
    {
        $key = $this->secrets($this->alice, $this->open($this->alice))->json('key');

        $stored = DB::table('team_docs')->value('key');
        $this->assertNotSame($key, $stored);
        $this->assertStringNotContainsString($key, $stored);
    }

    public function test_a_teammate_cannot_read_the_secrets_through_another_teammates_session()
    {
        $alice = $this->open($this->alice);
        $this->open($this->bob);

        $this->secrets($this->bob, $alice)->assertStatus(403)->assertJson(['error' => 'not_your_attempt']);
    }

    public function test_a_student_outside_the_team_gets_nothing()
    {
        $alice = $this->open($this->alice);

        $response = $this->secrets($this->dave, $alice)->assertStatus(403);

        $this->assertArrayNotHasKey('key', $response->json());
    }

    public function test_a_solo_attempt_has_no_team_document()
    {
        $solo = $this->open($this->dave, ['group_id' => null, 'team_started_at' => null]);

        $this->secrets($this->dave, $solo)->assertOk()->assertExactJson(['enabled' => false]);
        $this->assertSame(0, TeamDoc::count());
    }

    public function test_shared_typing_is_off_without_the_supabase_settings()
    {
        config(['services.team_sync.publishable_key' => null]);
        $alice = $this->open($this->alice);

        $this->secrets($this->alice, $alice)->assertOk()->assertExactJson(['enabled' => false]);
        $this->actingAs($this->alice)->getJson("/api/v1/sessions/{$alice->id}")->assertOk()->assertJsonPath('team_sync.enabled', false);
        $this->assertSame(0, TeamDoc::count());
    }

    public function test_a_finished_attempt_gets_no_secrets()
    {
        $done = $this->open($this->alice, ['status' => 'completed', 'ended_at' => now()]);

        $this->secrets($this->alice, $done)->assertOk()->assertExactJson(['enabled' => false]);
    }

    public function test_the_session_payload_says_whether_shared_typing_is_on()
    {
        $alice = $this->open($this->alice);
        $solo = $this->open($this->dave, ['group_id' => null, 'team_started_at' => null]);

        $this->actingAs($this->alice)->getJson("/api/v1/sessions/{$alice->id}")->assertOk()->assertJsonPath('team_sync.enabled', true);
        $this->actingAs($this->dave)->getJson("/api/v1/sessions/{$solo->id}")->assertOk()->assertJsonPath('team_sync.enabled', false);
    }

    public function test_updates_come_back_in_order_to_every_teammate()
    {
        $alice = $this->open($this->alice);
        $bob = $this->open($this->bob);
        $carol = $this->open($this->carol);

        $a = $this->append($this->alice, $alice, ['payload' => base64_encode('A')])->assertCreated()->json('id');
        $b = $this->append($this->bob, $bob, ['payload' => base64_encode('B')])->assertCreated()->json('id');

        $this->updates($this->carol, $carol)->assertOk()->assertExactJson([
            'updates' => [['id' => $a, 'payload' => base64_encode('A')], ['id' => $b, 'payload' => base64_encode('B')]],
            'last_update_id' => $b,
        ]);
        $this->updates($this->carol, $carol, $a)->assertOk()->assertJsonPath('updates', [['id' => $b, 'payload' => base64_encode('B')]]);
        $this->assertSame($b, $this->secrets($this->carol, $carol)->json('last_update_id'));
    }

    public function test_only_the_first_seed_is_kept()
    {
        $alice = $this->open($this->alice);
        $bob = $this->open($this->bob);

        $this->append($this->alice, $alice, ['payload' => base64_encode('seed-a'), 'seed' => true])->assertCreated();
        $this->append($this->bob, $bob, ['payload' => base64_encode('seed-b'), 'seed' => true])
            ->assertStatus(409)->assertJson(['error' => 'already_seeded']);

        $this->assertSame([base64_encode('seed-a')], TeamDocUpdate::pluck('payload')->all());
    }

    public function test_a_seed_is_refused_once_anyone_has_written()
    {
        $alice = $this->open($this->alice);

        $this->append($this->alice, $alice, ['payload' => base64_encode('typed')])->assertCreated();
        $this->append($this->alice, $alice, ['payload' => base64_encode('seed'), 'seed' => true])->assertStatus(409);

        $this->assertSame(1, TeamDocUpdate::count());
    }

    public function test_compaction_replaces_what_it_covers_and_keeps_later_updates()
    {
        $alice = $this->open($this->alice);
        $ids = [];
        foreach (['one', 'two', 'three'] as $text) {
            $ids[] = $this->append($this->alice, $alice, ['payload' => base64_encode($text)])->json('id');
        }

        $this->append($this->alice, $alice, ['payload' => base64_encode('whole'), 'replaces_through' => $ids[1]])->assertCreated();

        $this->assertSame([base64_encode('three'), base64_encode('whole')], TeamDocUpdate::orderBy('id')->pluck('payload')->all());
    }

    public function test_compaction_cannot_delete_another_teams_updates()
    {
        $other = Group::create(['lab_id' => $this->laboratory->id, 'name' => 'Team B', 'leader_id' => $this->dave->id]);
        $other->members()->attach($this->dave->id);
        $dave = $this->open($this->dave, ['group_id' => $other->id]);
        $theirs = $this->append($this->dave, $dave, ['payload' => base64_encode('theirs')])->json('id');
        $alice = $this->open($this->alice);

        $this->append($this->alice, $alice, ['payload' => base64_encode('ours'), 'replaces_through' => $theirs + 100])->assertCreated();

        $this->assertTrue(TeamDocUpdate::whereKey($theirs)->exists());
    }

    public function test_an_oversized_update_is_refused()
    {
        $alice = $this->open($this->alice);

        $this->append($this->alice, $alice, ['payload' => str_repeat('A', 700001)])->assertStatus(422);
        $this->append($this->alice, $alice, ['payload' => str_repeat('A', 700000)])->assertCreated();
    }

    public function test_an_update_that_is_not_base64_is_refused()
    {
        $alice = $this->open($this->alice);

        $this->append($this->alice, $alice, ['payload' => '<script>not base64</script>'])->assertStatus(422);
        $this->assertSame(0, TeamDocUpdate::count());
    }

    public function test_a_document_that_grew_too_long_asks_for_compaction()
    {
        $alice = $this->open($this->alice);
        $doc = TeamDoc::forSession($alice);
        $rows = array_map(fn () => ['team_doc_id' => $doc->id, 'user_id' => $this->alice->id, 'payload' => 'QQ==', 'created_at' => now()], range(1, 400));
        TeamDocUpdate::insert($rows);
        $last = TeamDocUpdate::max('id');

        $this->append($this->alice, $alice, ['payload' => 'Qg=='])
            ->assertStatus(409)->assertJson(['error' => 'compaction_needed', 'last_update_id' => $last]);
        $this->append($this->alice, $alice, ['payload' => 'Qw==', 'replaces_through' => $last])->assertCreated();

        $this->assertSame(1, TeamDocUpdate::count());
    }

    public function test_another_teams_updates_are_never_returned()
    {
        $other = Group::create(['lab_id' => $this->laboratory->id, 'name' => 'Team B', 'leader_id' => $this->dave->id]);
        $other->members()->attach($this->dave->id);
        $dave = $this->open($this->dave, ['group_id' => $other->id]);
        $this->append($this->dave, $dave, ['payload' => base64_encode('theirs')])->assertCreated();
        $alice = $this->open($this->alice);

        $this->updates($this->alice, $alice)->assertOk()->assertJsonPath('updates', []);
    }

    public function test_a_teammate_cannot_post_or_read_updates_through_another_teammates_session()
    {
        $alice = $this->open($this->alice);
        $this->open($this->bob);

        $this->append($this->bob, $alice, ['payload' => base64_encode('x')])->assertStatus(403)->assertJson(['error' => 'not_your_attempt']);
        $this->updates($this->bob, $alice)->assertStatus(403)->assertJson(['error' => 'not_your_attempt']);
        $this->assertSame(0, TeamDocUpdate::count());
    }

    public function test_updates_are_refused_where_there_is_no_team_document()
    {
        $solo = $this->open($this->dave, ['group_id' => null, 'team_started_at' => null]);

        $this->append($this->dave, $solo, ['payload' => base64_encode('x')])->assertStatus(409)->assertJson(['error' => 'team_sync_off']);
        $this->updates($this->dave, $solo)->assertStatus(409)->assertJson(['error' => 'team_sync_off']);
    }

    public function test_the_leaders_submit_deletes_the_teams_stored_document()
    {
        $this->mock(SandboxExecutionService::class)->shouldReceive('execute')
            ->andReturn(['status' => 'success', 'output' => '1', 'errors' => null, 'execution_time_ms' => 5]);
        $this->app->instance(LlmEvaluationService::class, new class extends LlmEvaluationService
        {
            public function __construct() {}

            public function evaluate(LabSession $session, string $code, string $language, array $diagnostics = [], array $otherFiles = []): array
            {
                return ['correctness_score' => 80.0, 'tasks' => [['id' => 1, 'completed' => true]]];
            }
        });
        $alice = $this->open($this->alice);
        $bob = $this->open($this->bob);
        $this->append($this->bob, $bob, ['payload' => base64_encode('typed')])->assertCreated();

        $this->actingAs($this->alice)->postJson("/api/v1/sessions/{$alice->id}/submit", ['code' => 'class Main {}', 'language' => 'java'])->assertOk();

        $this->assertSame(0, TeamDoc::count());
        $this->assertSame(0, TeamDocUpdate::count());
    }

    public function test_stored_documents_of_ended_team_attempts_are_pruned()
    {
        $this->laboratory->update(['max_attempts' => 3]);
        $old = $this->open($this->alice);
        $this->append($this->alice, $old, ['payload' => base64_encode('old')])->assertCreated();
        $old->update(['status' => 'abandoned', 'ended_at' => now()]);

        $liveStart = now()->startOfSecond();
        $live = $this->open($this->bob, ['team_started_at' => $liveStart, 'started_at' => $liveStart]);
        $this->append($this->bob, $live, ['payload' => base64_encode('live')])->assertCreated();

        $this->artisan('certicode:prune-team-docs')->assertSuccessful();

        $this->assertSame([base64_encode('live')], TeamDocUpdate::pluck('payload')->all());
        $this->assertSame(1, TeamDoc::count());
    }

    public function test_a_session_reopened_after_the_team_submitted_gets_no_team_document()
    {
        // The leader submitted: every teammate's attempt was closed with the team's grade.
        // The instructor then reopened Bob's alone, as a solo attempt.
        $this->open($this->alice, ['status' => 'completed', 'ended_at' => now()]);
        $bob = $this->open($this->bob);

        $this->secrets($this->bob, $bob)->assertOk()->assertExactJson(['enabled' => false]);
        $this->actingAs($this->bob)->getJson("/api/v1/sessions/{$bob->id}")->assertOk()->assertJsonPath('team_sync.enabled', false);
        $this->assertSame(0, TeamDoc::count());
    }

    public function test_an_instructor_cannot_get_the_key_or_write_into_the_teams_files()
    {
        $alice = $this->open($this->alice);
        $instructor = User::where('role', 'instructor')->first();

        $response = $this->actingAs($instructor)->getJson("/api/v1/sessions/{$alice->id}/team-doc")->assertStatus(403);
        $this->assertArrayNotHasKey('key', $response->json());
        $this->actingAs($instructor)->postJson("/api/v1/sessions/{$alice->id}/team-doc/updates", ['payload' => base64_encode('x')])->assertStatus(403);
        $this->actingAs($instructor)->getJson("/api/v1/sessions/{$alice->id}/team-doc/updates")->assertStatus(403);
        $this->assertSame(0, TeamDoc::count());
        $this->assertSame(0, TeamDocUpdate::count());
    }

    public function test_removing_a_teammate_gives_the_team_a_new_channel_and_key()
    {
        $alice = $this->open($this->alice);
        $this->open($this->carol);
        $before = $this->secrets($this->alice, $alice)->json();
        $rotationBefore = $this->actingAs($this->alice)->getJson("/api/v1/sessions/{$alice->id}")->json('team_sync.rotation');

        // The instructor moves Carol out of the team while the lab is under way
        app(LabTeams::class)->moveOpenAttempts($this->laboratory, $this->team, [], [$this->carol->id]);

        $after = $this->secrets($this->alice, $alice)->json();
        $this->assertNotSame($before['topic'], $after['topic']);
        $this->assertNotSame($before['key'], $after['key']);
        $this->assertNotSame($rotationBefore, $this->actingAs($this->alice)->getJson("/api/v1/sessions/{$alice->id}")->json('team_sync.rotation'));
    }

    public function test_adding_a_teammate_keeps_the_channel_and_key()
    {
        $alice = $this->open($this->alice);
        $before = $this->secrets($this->alice, $alice)->json();

        app(LabTeams::class)->moveOpenAttempts($this->laboratory, $this->team, [$this->dave->id], []);

        $this->assertSame($before['key'], $this->secrets($this->alice, $alice)->json('key'));
    }

    public function test_the_size_check_reads_a_kept_total_not_every_stored_update()
    {
        $alice = $this->open($this->alice);
        $doc = TeamDoc::forSession($alice);
        $this->append($this->alice, $alice, ['payload' => base64_encode('one')])->assertCreated();
        $this->append($this->alice, $alice, ['payload' => base64_encode('two')])->assertCreated();

        $this->assertSame(strlen(base64_encode('one')) + strlen(base64_encode('two')), (int) $doc->fresh()->payload_chars);

        $ids = TeamDocUpdate::orderBy('id')->pluck('id');
        $this->append($this->alice, $alice, ['payload' => base64_encode('whole'), 'replaces_through' => $ids[1]])->assertCreated();
        $this->assertSame(strlen(base64_encode('whole')), (int) $doc->fresh()->payload_chars);

        // Past the size limit, a plain append asks for a compaction
        $doc->update(['payload_chars' => TeamDoc::MAX_PAYLOAD_CHARS]);
        $this->append($this->alice, $alice, ['payload' => base64_encode('more')])->assertStatus(409)->assertJson(['error' => 'compaction_needed']);
    }
}
