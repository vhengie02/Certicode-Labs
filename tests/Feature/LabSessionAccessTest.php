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

class LabSessionAccessTest extends TestCase
{
    use RefreshDatabase;

    protected User $instructor;
    protected User $alice;
    protected User $bob;
    protected Laboratory $laboratory;
    protected LabSession $aliceSession;

    protected function setUp(): void
    {
        parent::setUp();

        $this->instructor = User::create(['name' => 'Prof', 'email' => 'prof@example.com', 'password' => bcrypt('password'), 'role' => 'instructor']);
        $this->alice = User::create(['name' => 'Alice', 'email' => 'alice@example.com', 'password' => bcrypt('password'), 'role' => 'student']);
        $this->bob = User::create(['name' => 'Bob', 'email' => 'bob@example.com', 'password' => bcrypt('password'), 'role' => 'student']);

        $class = SchoolClass::create(['name' => 'CS 101', 'code' => 'CS101', 'instructor_id' => $this->instructor->id]);
        $module = Module::create(['class_id' => $class->id, 'title' => 'Module 1', 'content' => 'Content', 'order_index' => 0]);
        $this->laboratory = Laboratory::create([
            'module_id' => $module->id,
            'title' => 'Lab 1',
            'description' => 'Description',
            'time_limit' => 45,
            'tasks_definition' => [['id' => 1, 'task' => 'Task']],
        ]);

        $this->aliceSession = LabSession::create([
            'lab_id' => $this->laboratory->id,
            'user_id' => $this->alice->id,
            'status' => 'in_progress',
            'started_at' => now(),
        ]);
    }

    public function test_anonymous_requests_to_extension_api_are_rejected()
    {
        $this->getJson("/api/v1/sessions/{$this->aliceSession->id}")->assertStatus(401);
        $this->postJson("/api/v1/sessions/{$this->aliceSession->id}/chat", ['message' => 'hi'])->assertStatus(401);
        $this->postJson("/api/v1/sessions/{$this->aliceSession->id}/end")->assertStatus(401);

        $this->assertSame('in_progress', $this->aliceSession->fresh()->status);
    }

    public function test_wrong_or_other_sessions_token_is_rejected()
    {
        $bobSession = LabSession::create(['lab_id' => $this->laboratory->id, 'user_id' => $this->bob->id, 'status' => 'in_progress']);

        $this->withHeaders(['X-Session-Token' => 'not-the-token'])
            ->getJson("/api/v1/sessions/{$this->aliceSession->id}")->assertStatus(401);

        $this->withHeaders(['X-Session-Token' => $bobSession->issueExtensionToken()])
            ->getJson("/api/v1/sessions/{$this->aliceSession->id}")->assertStatus(401);
    }

    public function test_session_token_grants_access_and_is_never_serialized()
    {
        $token = $this->aliceSession->issueExtensionToken();

        $response = $this->withHeaders(['X-Session-Token' => $token])
            ->getJson("/api/v1/sessions/{$this->aliceSession->id}")
            ->assertOk();

        $this->assertStringNotContainsString($token, $response->getContent());
        $this->assertSame($token, $this->aliceSession->fresh()->issueExtensionToken(), 'token is stable once issued');
    }

    public function test_token_encrypted_with_a_previous_app_key_is_treated_as_missing()
    {
        // Simulate an APP_KEY rotation: the stored ciphertext no longer decrypts.
        $oldKey = new \Illuminate\Encryption\Encrypter(random_bytes(32), 'AES-256-CBC');
        \Illuminate\Support\Facades\DB::table('lab_sessions')
            ->where('id', $this->aliceSession->id)
            ->update(['extension_token' => $oldKey->encryptString('old-token')]);
        $session = $this->aliceSession->fresh();

        $this->withHeaders(['X-Session-Token' => 'old-token'])
            ->getJson("/api/v1/sessions/{$session->id}")->assertStatus(401);

        $newToken = $session->issueExtensionToken();
        $this->assertNotSame('old-token', $newToken);
        $this->withHeaders(['X-Session-Token' => $newToken])
            ->getJson("/api/v1/sessions/{$session->id}")->assertOk();
    }

    public function test_signed_in_users_need_access_to_the_session()
    {
        $this->actingAs($this->bob)->getJson("/v1/sessions/{$this->aliceSession->id}")->assertStatus(403);
        $this->actingAs($this->alice)->getJson("/v1/sessions/{$this->aliceSession->id}")->assertOk();
        $this->actingAs($this->instructor)->getJson("/v1/sessions/{$this->aliceSession->id}")->assertOk();
    }

    public function test_teammates_in_the_same_group_can_access_each_others_session()
    {
        $group = Group::create(['name' => 'Team A', 'lab_id' => $this->laboratory->id]);
        $this->aliceSession->update(['group_id' => $group->id]);
        LabSession::create(['lab_id' => $this->laboratory->id, 'user_id' => $this->bob->id, 'group_id' => $group->id, 'status' => 'in_progress']);

        $this->actingAs($this->bob)->getJson("/v1/sessions/{$this->aliceSession->id}")->assertOk();
    }

    public function test_only_staff_can_reopen_a_session()
    {
        $this->aliceSession->update(['status' => 'completed']);

        $this->actingAs($this->alice)->postJson("/v1/sessions/{$this->aliceSession->id}/reopen")->assertStatus(403);
        $this->assertSame('completed', $this->aliceSession->fresh()->status);

        $this->actingAs($this->instructor)->postJson("/v1/sessions/{$this->aliceSession->id}/reopen")->assertOk();
        $this->assertSame('in_progress', $this->aliceSession->fresh()->status);
    }

    public function test_vscode_deep_link_carries_the_session_token()
    {
        $response = $this->actingAs($this->alice)
            ->postJson("/laboratories/{$this->laboratory->id}/start", ['camera_verified' => true])
            ->assertOk();

        $session = LabSession::find($response->json('session_id'));
        $this->assertStringContainsString('token=' . urlencode($session->issueExtensionToken()), $response->json('vscode_url'));
    }
}
