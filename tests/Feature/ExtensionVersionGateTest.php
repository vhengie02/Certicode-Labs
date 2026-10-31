<?php

namespace Tests\Feature;

use App\Models\Laboratory;
use App\Models\LabSession;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExtensionVersionGateTest extends TestCase
{
    use RefreshDatabase;

    protected User $student;

    protected LabSession $session;

    protected string $token;

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.vscode_extension.required_version' => '1.5.0']);

        $this->student = User::create(['name' => 'Jane Student', 'email' => 'student@example.com', 'password' => bcrypt('password'), 'role' => 'student']);
        $laboratory = Laboratory::create([
            'title' => 'Lab 1',
            'description' => 'Description',
            'time_limit' => 45,
            'tasks_definition' => [['id' => 1, 'task' => 'Task']],
        ]);
        $this->session = LabSession::create([
            'lab_id' => $laboratory->id,
            'user_id' => $this->student->id,
            'status' => 'in_progress',
            'started_at' => now(),
        ]);
        $this->token = $this->session->issueExtensionToken();
    }

    public function test_build_without_version_header_using_bearer_auth_gets_426()
    {
        // Builds before 1.3.0 send the session token as a Bearer token and no version
        $this->withHeaders(['Authorization' => "Bearer {$this->token}"])
            ->getJson("/api/v1/sessions/{$this->session->id}")
            ->assertStatus(426)
            ->assertJson([
                'error' => 'extension_outdated',
                'min_version' => '1.5.0',
                'download_url' => asset('downloads/certicode-labs.vsix'),
            ]);
    }

    public function test_version_below_the_minimum_gets_426_on_both_route_prefixes()
    {
        $headers = ['X-Session-Token' => $this->token, 'X-Extension-Version' => '1.4.9'];

        $this->withHeaders($headers)
            ->getJson("/api/v1/sessions/{$this->session->id}")
            ->assertStatus(426)
            ->assertJson(['error' => 'extension_outdated', 'min_version' => '1.5.0']);

        // On Vercel the extension's /api/v1 calls arrive as /v1
        $this->withHeaders($headers)
            ->postJson("/v1/sessions/{$this->session->id}/ping", ['source' => 'vscode'])
            ->assertStatus(426);
    }

    public function test_garbled_version_header_gets_426()
    {
        $this->withHeaders(['X-Session-Token' => $this->token, 'X-Extension-Version' => 'latest'])
            ->getJson("/api/v1/sessions/{$this->session->id}")
            ->assertStatus(426);
    }

    public function test_current_version_with_valid_token_passes_through()
    {
        $this->withHeaders(['X-Session-Token' => $this->token, 'X-Extension-Version' => '1.7.1', 'X-Client' => 'vscode'])
            ->getJson("/api/v1/sessions/{$this->session->id}")
            ->assertOk()
            ->assertJsonPath('session_id', $this->session->id);
    }

    public function test_current_version_with_wrong_token_is_still_unauthorized()
    {
        $this->withHeaders(['X-Session-Token' => 'not-the-token', 'X-Extension-Version' => '1.7.1'])
            ->getJson("/api/v1/sessions/{$this->session->id}")
            ->assertStatus(401);
    }

    public function test_session_token_builds_from_before_version_reporting_still_pass()
    {
        // 1.3.0 to 1.5.0 send X-Session-Token but no X-Extension-Version
        $this->withHeaders(['X-Session-Token' => $this->token])
            ->getJson("/api/v1/sessions/{$this->session->id}")
            ->assertOk();
    }

    public function test_signed_in_browser_requests_are_not_checked()
    {
        $this->actingAs($this->student)
            ->getJson("/v1/sessions/{$this->session->id}")
            ->assertOk();
    }
}
