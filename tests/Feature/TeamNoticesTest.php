<?php

namespace Tests\Feature;

use App\Events\ChatMessageSent;
use App\Models\Group;
use App\Models\Laboratory;
use App\Models\LabSessionChat;
use App\Models\Module;
use App\Models\SchoolClass;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class TeamNoticesTest extends TestCase
{
    use RefreshDatabase;

    protected User $alice;

    protected User $bob;

    protected Laboratory $laboratory;

    protected function setUp(): void
    {
        parent::setUp();

        $instructor = User::create(['name' => 'Prof', 'email' => 'prof@example.com', 'password' => bcrypt('password'), 'role' => 'instructor']);
        $this->alice = User::create(['name' => 'Alice', 'email' => 'alice@example.com', 'password' => bcrypt('password'), 'role' => 'student']);
        $this->bob = User::create(['name' => 'Bob', 'email' => 'bob@example.com', 'password' => bcrypt('password'), 'role' => 'student']);

        $class = SchoolClass::create(['name' => 'CS 101', 'code' => 'CS101', 'instructor_id' => $instructor->id]);
        $class->students()->attach([$this->alice->id => ['status' => 'enrolled'], $this->bob->id => ['status' => 'enrolled']]);
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

    private function team(): Group
    {
        $team = Group::create(['lab_id' => $this->laboratory->id, 'name' => 'Team A']);
        $team->members()->attach([$this->alice->id, $this->bob->id]);

        return $team;
    }

    private function chatsSeenBy(User $student, int $sessionId): array
    {
        return $this->actingAs($student)->getJson("/api/v1/sessions/{$sessionId}/chat")->assertOk()->json('chats');
    }

    public function test_teammates_see_a_notice_when_someone_opens_the_lab()
    {
        $this->team();
        $aliceSession = $this->laboratory->findOrStartAttemptFor($this->alice);
        $this->laboratory->findOrStartAttemptFor($this->bob);

        $chats = $this->chatsSeenBy($this->alice, $aliceSession->id);

        $notice = collect($chats)->firstWhere('message', 'Bob joined the lab.');
        $this->assertNotNull($notice);
        $this->assertTrue($notice['is_system']);
    }

    public function test_a_notice_is_broadcast_to_every_teammate_in_the_lab()
    {
        $this->team();
        $aliceSession = $this->laboratory->findOrStartAttemptFor($this->alice);
        Event::fake([ChatMessageSent::class]);

        $bobSession = $this->laboratory->findOrStartAttemptFor($this->bob);

        foreach ([$aliceSession->id, $bobSession->id] as $sessionId) {
            Event::assertDispatched(ChatMessageSent::class, fn ($e) => $e->sessionId === $sessionId && $e->chat['message'] === 'Bob joined the lab.');
        }
    }

    public function test_resuming_an_open_attempt_posts_no_second_notice()
    {
        $this->team();
        $this->laboratory->findOrStartAttemptFor($this->bob);
        $this->laboratory->findOrStartAttemptFor($this->bob);

        $this->assertSame(1, LabSessionChat::where('message', 'Bob joined the lab.')->count());
    }

    public function test_a_message_typed_by_a_student_is_not_a_system_notice()
    {
        $this->team();
        $session = $this->laboratory->findOrStartAttemptFor($this->alice);

        $this->actingAs($this->alice)->postJson("/api/v1/sessions/{$session->id}/chat", ['message' => 'Alice joined the lab.'])
            ->assertOk()->assertJsonPath('chat.is_system', false);
    }

    public function test_a_solo_lab_posts_no_notice()
    {
        $this->laboratory->update(['is_group_lab' => false]);

        $this->laboratory->findOrStartAttemptFor($this->alice);

        $this->assertSame(0, LabSessionChat::count());
    }

    public function test_a_student_without_a_team_posts_no_notice()
    {
        $this->laboratory->findOrStartAttemptFor($this->alice);

        $this->assertSame(0, LabSessionChat::count());
    }

    public function test_teammates_see_a_notice_when_someone_closes_vs_code()
    {
        $this->team();
        $aliceSession = $this->laboratory->findOrStartAttemptFor($this->alice);
        $bobSession = $this->laboratory->findOrStartAttemptFor($this->bob);

        $this->actingAs($this->bob)->postJson("/api/v1/sessions/{$bobSession->id}/vscode-disconnect", ['source' => 'vscode'])->assertOk();

        $notice = collect($this->chatsSeenBy($this->alice, $aliceSession->id))->firstWhere('message', 'Bob left the lab.');
        $this->assertNotNull($notice);
        $this->assertTrue($notice['is_system']);
    }

    public function test_closing_vs_code_on_a_finished_attempt_posts_no_notice()
    {
        $this->team();
        $bobSession = $this->laboratory->findOrStartAttemptFor($this->bob);
        $bobSession->update(['status' => 'completed', 'ended_at' => now()]);

        $this->actingAs($this->bob)->postJson("/api/v1/sessions/{$bobSession->id}/vscode-disconnect", ['source' => 'vscode'])->assertOk();

        $this->assertSame(0, LabSessionChat::where('message', 'Bob left the lab.')->count());
    }
}
