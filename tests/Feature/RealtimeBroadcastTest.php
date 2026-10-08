<?php

namespace Tests\Feature;

use App\Events\AnomalyDetected;
use App\Models\Laboratory;
use App\Models\LabSession;
use App\Models\Module;
use App\Models\SchoolClass;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RealtimeBroadcastTest extends TestCase
{
    use RefreshDatabase;

    protected LabSession $session;
    protected string $token;

    protected function setUp(): void
    {
        parent::setUp();

        $instructor = User::create(['name' => 'Prof', 'email' => 'prof@example.com', 'password' => bcrypt('password'), 'role' => 'instructor']);
        $student = User::create(['name' => 'Ana', 'email' => 'ana@example.com', 'password' => bcrypt('password'), 'role' => 'student']);
        $class = SchoolClass::create(['name' => 'CS 101', 'code' => 'CS101', 'instructor_id' => $instructor->id]);
        $module = Module::create(['class_id' => $class->id, 'title' => 'Module 1', 'content' => 'Content', 'order_index' => 0]);
        $lab = Laboratory::create(['module_id' => $module->id, 'title' => 'Lab 1', 'description' => 'D', 'time_limit' => 45, 'tasks_definition' => [['id' => 1, 'task' => 'Task']]]);

        $this->session = LabSession::create(['lab_id' => $lab->id, 'user_id' => $student->id, 'status' => 'in_progress', 'started_at' => now()]);
        $this->token = $this->session->issueExtensionToken();
    }

    private function enablePusher(): void
    {
        config([
            'broadcasting.default' => 'pusher',
            'broadcasting.connections.pusher.key' => 'test-key',
            'broadcasting.connections.pusher.secret' => 'test-secret',
            'broadcasting.connections.pusher.app_id' => '12345',
            'broadcasting.connections.pusher.options.cluster' => 'ap1',
        ]);
    }

    private function auth(string $channel)
    {
        return $this->withHeaders(['X-Session-Token' => $this->token])
            ->postJson("/api/v1/sessions/{$this->session->id}/broadcasting/auth", ['socket_id' => '1234.5678', 'channel_name' => $channel]);
    }

    public function test_session_payload_advertises_realtime_only_when_configured()
    {
        $headers = ['X-Session-Token' => $this->token];
        $this->withHeaders($headers)->getJson("/api/v1/sessions/{$this->session->id}")->assertJsonPath('realtime', null);

        $this->enablePusher();
        $this->withHeaders($headers)->getJson("/api/v1/sessions/{$this->session->id}")
            ->assertJsonPath('realtime.key', 'test-key')
            ->assertJsonPath('realtime.ws_host', 'ws-ap1.pusher.com')
            ->assertJsonMissingPath('realtime.secret');
    }

    public function test_extension_can_sign_its_own_session_channels()
    {
        $this->enablePusher();
        $channel = "private-lab-session.{$this->session->id}";

        $expected = 'test-key:' . hash_hmac('sha256', "1234.5678:{$channel}", 'test-secret');
        $this->auth($channel)->assertOk()->assertJsonPath('auth', $expected);
        $this->auth("{$channel}.chat")->assertOk();
    }

    public function test_extension_cannot_sign_other_channels()
    {
        $this->enablePusher();

        $this->auth('private-lab-session.' . ($this->session->id + 1))->assertStatus(403);
        $this->auth("private-instructor.lab.{$this->session->lab_id}")->assertStatus(403);
    }

    public function test_signing_requires_the_session_token()
    {
        $this->enablePusher();

        $this->postJson("/api/v1/sessions/{$this->session->id}/broadcasting/auth", ['socket_id' => '1234.5678', 'channel_name' => "private-lab-session.{$this->session->id}"])
            ->assertStatus(401);
    }

    public function test_signing_is_unavailable_when_realtime_is_off()
    {
        $this->auth("private-lab-session.{$this->session->id}")->assertStatus(404);
    }

    public function test_instructor_events_go_to_the_lab_channel()
    {
        $channels = array_map(fn ($c) => $c->name, (new AnomalyDetected($this->session->id, ['id' => 1]))->broadcastOn());

        $this->assertSame(["private-instructor.lab.{$this->session->lab_id}"], $channels);
    }
}
