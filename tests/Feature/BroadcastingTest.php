<?php

namespace Tests\Feature;

use App\Events\AnomalyDetected;
use App\Events\ChatMessageSent;
use App\Events\DiffUpdated;
use App\Events\LeaderboardUpdated;
use App\Models\Laboratory;
use App\Models\LabSession;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class BroadcastingTest extends TestCase
{
    use RefreshDatabase;

    protected User $student;
    protected Laboratory $lab;
    protected LabSession $session;

    protected function setUp(): void
    {
        parent::setUp();

        $this->student = User::create([
            'name' => 'Alice Dev',
            'email' => 'alice@example.com',
            'password' => bcrypt('password'),
            'role' => 'student',
        ]);

        $this->lab = Laboratory::create([
            'title' => 'Broadcast Testing Lab',
            'description' => 'Test broadcast descriptions',
            'time_limit' => 30,
            'is_group_lab' => false,
            'tasks_definition' => [
                ['id' => 1, 'task' => 'Implement solution', 'command' => 'regex:class'],
            ],
            'starter_files' => [
                ['name' => 'Solution.java', 'content' => 'public class Solution {}', 'is_primary' => true],
            ],
        ]);

        $this->session = LabSession::create([
            'lab_id' => $this->lab->id,
            'user_id' => $this->student->id,
            'status' => 'in_progress',
            'started_at' => now(),
            'performance_score' => 0.0,
        ]);
    }

    public function test_record_diff_dispatches_diff_updated_event(): void
    {
        Event::fake([DiffUpdated::class]);

        $response = $this->actingAs($this->student)->postJson("/api/v1/sessions/{$this->session->id}/diff", [
            'lines_added' => 12,
            'lines_deleted' => 3,
            'lines_modified' => 2,
            'files' => [
                ['name' => 'Solution.java', 'added' => 12, 'deleted' => 3, 'modified' => 2],
            ],
        ]);

        $response->assertStatus(200);

        Event::assertDispatched(DiffUpdated::class, function ($event) {
            return $event->sessionId === $this->session->id
                && $event->diffStats['lines_added'] === 12
                && $event->diffStats['lines_deleted'] === 3
                && count($event->contributions) > 0;
        });
    }

    public function test_send_chat_dispatches_chat_message_sent_event(): void
    {
        Event::fake([ChatMessageSent::class]);

        $response = $this->actingAs($this->student)->postJson("/api/v1/sessions/{$this->session->id}/chat", [
            'message' => 'Hey team, check line 45 for the null pointer fix!',
            'code_snippet' => 'if (obj != null) { ... }',
        ]);

        $response->assertStatus(200);

        Event::assertDispatched(ChatMessageSent::class, function ($event) {
            return $event->sessionId === $this->session->id
                && $event->chat['message'] === 'Hey team, check line 45 for the null pointer fix!'
                && $event->chat['user_id'] === $this->student->id;
        });
    }

    public function test_check_progress_dispatches_leaderboard_updated_event(): void
    {
        Event::fake([LeaderboardUpdated::class]);

        $response = $this->actingAs($this->student)->postJson("/api/v1/sessions/{$this->session->id}/check-progress", [
            'code' => 'public class Solution { public static void main(String[] args) {} }',
            'language' => 'java',
        ]);

        $response->assertStatus(200);

        Event::assertDispatched(LeaderboardUpdated::class, function ($event) {
            return $event->sessionId === $this->session->id
                && isset($event->leaderboard['leaderboard']);
        });
    }

    public function test_paste_anomaly_dispatches_anomaly_detected_event(): void
    {
        Event::fake([AnomalyDetected::class]);

        $response = $this->actingAs($this->student)->postJson("/api/v1/sessions/{$this->session->id}/telemetry", [
            'event_type' => 'paste_anomaly',
            'payload' => [
                'pasted_length' => 250,
                'snippet' => 'public static void hugeExternalCopyPasteMethod() {}',
            ],
        ]);

        $response->assertStatus(200);

        Event::assertDispatched(AnomalyDetected::class, function ($event) {
            return $event->sessionId === $this->session->id
                && $event->anomaly['type'] === 'paste_anomaly'
                && $event->anomaly['severity'] === 'high';
        });
    }
}
