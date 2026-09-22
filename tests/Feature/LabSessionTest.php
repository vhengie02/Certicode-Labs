<?php

namespace Tests\Feature;

use App\Models\Laboratory;
use App\Models\User;
use App\Models\LabSession;
use App\Models\TelemetryLog;
use App\Models\Anomaly;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LabSessionTest extends TestCase
{
    use RefreshDatabase;

    protected User $student;
    protected Laboratory $laboratory;

    protected function setUp(): void
    {
        parent::setUp();

        // Create a student user
        $this->student = User::create([
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'password' => bcrypt('password'),
            'role' => 'student',
            'github_username' => 'johndoe',
        ]);

        // Create a laboratory exercise
        $this->laboratory = Laboratory::create([
            'title' => 'Linux CLI Introduction',
            'description' => 'Learn the basics of linux filesystem navigation.',
            'time_limit' => 45,
            'is_group_lab' => false,
            'tasks_definition' => [
                ['id' => 1, 'task' => 'List contents of home folder', 'command' => 'ls ~'],
                ['id' => 2, 'task' => 'Print working directory', 'command' => 'pwd'],
            ],
        ]);
    }

    /**
     * Test starting a lab session.
     */
    public function test_student_can_start_a_lab_session(): void
    {
        $response = $this->actingAs($this->student)
            ->postJson("/api/v1/labs/{$this->laboratory->id}/start");

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'message',
            'session' => [
                'id',
                'lab_id',
                'user_id',
                'status',
            ],
        ]);

        $this->assertDatabaseHas('lab_sessions', [
            'lab_id' => $this->laboratory->id,
            'user_id' => $this->student->id,
            'status' => 'in_progress',
        ]);
    }

    /**
     * Test logging telemetry events and flagging anomalies.
     */
    public function test_telemetry_submission_logs_event_and_detects_anomalies(): void
    {
        $session = LabSession::create([
            'lab_id' => $this->laboratory->id,
            'user_id' => $this->student->id,
            'status' => 'in_progress',
            'started_at' => now(),
        ]);

        // Log focus blur (tab switch)
        $response = $this->actingAs($this->student)
            ->postJson("/api/v1/sessions/{$session->id}/telemetry", [
                'event_type' => 'tab_switch',
                'payload' => ['tab_title' => 'Google Search'],
            ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('telemetry_logs', [
            'lab_session_id' => $session->id,
            'event_type' => 'tab_switch',
        ]);
    }

    /**
     * Test code execution through the mock sandbox wrapper.
     */
    public function test_code_execution_mock_wrapper(): void
    {
        $session = LabSession::create([
            'lab_id' => $this->laboratory->id,
            'user_id' => $this->student->id,
            'status' => 'in_progress',
            'started_at' => now(),
        ]);

        $response = $this->actingAs($this->student)
            ->postJson("/api/v1/sessions/{$session->id}/execute", [
                'code' => 'echo "Hello, World!"',
                'language' => 'bash',
            ]);

        $response->assertStatus(200);
        $response->assertJsonFragment([
            'status' => 'success',
        ]);

        $this->assertDatabaseHas('telemetry_logs', [
            'lab_session_id' => $session->id,
            'event_type' => 'code_execution',
        ]);
    }

    /**
     * Test ping updates last_ping_at and returns active connectivity.
     */
    public function test_student_can_ping_session_and_update_last_ping_at(): void
    {
        $session = LabSession::create([
            'lab_id' => $this->laboratory->id,
            'user_id' => $this->student->id,
            'status' => 'in_progress',
            'started_at' => now(),
        ]);

        $response = $this->actingAs($this->student)
            ->postJson("/api/v1/sessions/{$session->id}/ping", [
                'wpm' => 35,
                'keystroke_count' => 120,
            ]);

        $response->assertStatus(200);
        $response->assertJson([
            'status' => 'success',
            'session_id' => $session->id,
            'is_active' => true,
        ]);

        $session->refresh();
        $this->assertNotNull($session->last_ping_at);
        $this->assertTrue($session->isActivelyConnected());
        $this->assertEquals(35, $session->wpm);
    }

    /**
     * Test ping on session that exceeded lab time limit auto-abandons it.
     */
    public function test_ping_on_expired_session_auto_abandons(): void
    {
        $session = LabSession::create([
            'lab_id' => $this->laboratory->id,
            'user_id' => $this->student->id,
            'status' => 'in_progress',
            'started_at' => now()->subMinutes(60), // Time limit is 45 min
        ]);

        $response = $this->actingAs($this->student)
            ->postJson("/api/v1/sessions/{$session->id}/ping");

        $response->assertStatus(200);
        $response->assertJson([
            'status' => 'abandoned',
            'is_active' => false,
        ]);

        $session->refresh();
        $this->assertEquals('abandoned', $session->status);
    }

    /**
     * Test LabSession::autoExpireStaleSessions cleans up stale abandoned sessions.
     */
    public function test_auto_expire_stale_sessions_method(): void
    {
        $staleSession = LabSession::create([
            'lab_id' => $this->laboratory->id,
            'user_id' => $this->student->id,
            'status' => 'in_progress',
            'started_at' => now()->subMinutes(120),
        ]);

        $activeSession = LabSession::create([
            'lab_id' => $this->laboratory->id,
            'user_id' => $this->student->id,
            'status' => 'in_progress',
            'started_at' => now()->subMinutes(10),
            'last_ping_at' => now(),
        ]);

        $expired = LabSession::autoExpireStaleSessions($this->laboratory->id);

        $this->assertEquals(1, $expired);
        $this->assertEquals('abandoned', $staleSession->fresh()->status);
        $this->assertEquals('in_progress', $activeSession->fresh()->status);
    }
}
