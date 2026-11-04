<?php

namespace Tests\Feature;

use App\Models\Laboratory;
use App\Models\LabSession;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * Regression tests for state the extension reports to the browser lab page. On Vercel the
 * cache store is "array", which is empty on every request, so the cache is flushed between
 * requests here: the extension version and a clean VS Code shutdown must still get through.
 */
class VSCodeStateAcrossInstancesTest extends TestCase
{
    use RefreshDatabase;

    private User $student;

    private LabSession $session;

    protected function setUp(): void
    {
        parent::setUp();

        $this->student = User::create(['name' => 'Student', 'email' => 'student@example.com', 'password' => bcrypt('password'), 'role' => 'student']);
        $lab = Laboratory::create(['title' => 'Loops', 'description' => 'Write a loop.', 'time_limit' => 30, 'is_group_lab' => false, 'tasks_definition' => []]);
        $this->session = LabSession::create(['lab_id' => $lab->id, 'user_id' => $this->student->id, 'status' => 'in_progress', 'started_at' => now()]);
        $this->actingAs($this->student);
    }

    private function newInstance(): void
    {
        Cache::flush();
    }

    public function test_the_lab_page_sees_an_outdated_extension_reported_on_another_instance(): void
    {
        config(['services.vscode_extension.min_version' => '1.6.0']);

        $this->withHeaders(['X-Extension-Version' => '1.5.0'])
            ->postJson("/api/v1/sessions/{$this->session->id}/ping", ['source' => 'vscode'])
            ->assertOk();

        $this->newInstance();
        $this->withHeaders(['X-Extension-Version' => '', 'X-Client' => ''])
            ->getJson("/v1/sessions/{$this->session->id}")
            ->assertJson(['extension_outdated' => true, 'extension_version' => '1.5.0']);

        $this->newInstance();
        $this->withHeaders(['X-Extension-Version' => '1.7.0', 'X-Client' => 'vscode'])
            ->getJson("/api/v1/sessions/{$this->session->id}")
            ->assertOk();

        $this->newInstance();
        $this->withHeaders(['X-Extension-Version' => '', 'X-Client' => ''])
            ->getJson("/v1/sessions/{$this->session->id}")
            ->assertJson(['extension_outdated' => false, 'extension_version' => '1.7.0']);
    }

    public function test_the_lab_page_sees_a_clean_vscode_shutdown_reported_on_another_instance(): void
    {
        $this->postJson("/api/v1/sessions/{$this->session->id}/ping", ['source' => 'vscode'])->assertOk();

        $this->newInstance();
        $this->getJson("/v1/sessions/{$this->session->id}")
            ->assertJson(['vscode_connected' => true, 'vscode_closed' => false]);

        $this->postJson("/api/v1/sessions/{$this->session->id}/vscode-disconnect", ['source' => 'vscode'])->assertOk();

        $this->newInstance();
        $this->getJson("/v1/sessions/{$this->session->id}")
            ->assertJson(['vscode_connected' => false, 'vscode_closed' => false, 'vscode_reconnecting' => true]);

        // Not a reload: nothing resumed within the grace period
        $this->travel(31)->seconds();
        $this->newInstance();
        $this->getJson("/v1/sessions/{$this->session->id}")
            ->assertJson(['vscode_connected' => false, 'vscode_closed' => true]);

        // VS Code reopened
        $this->postJson("/api/v1/sessions/{$this->session->id}/ping", ['source' => 'vscode'])->assertOk();

        $this->newInstance();
        $this->getJson("/v1/sessions/{$this->session->id}")
            ->assertJson(['vscode_connected' => true, 'vscode_closed' => false]);
    }
}
