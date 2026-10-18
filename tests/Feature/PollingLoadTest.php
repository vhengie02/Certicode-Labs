<?php

namespace Tests\Feature;

use App\Models\Laboratory;
use App\Models\LabSession;
use App\Models\Module;
use App\Models\SchoolClass;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Polling should cost reads, not writes: the extension's cookieless calls create no session
 * rows, and the lab page reading the session is not a heartbeat.
 */
class PollingLoadTest extends TestCase
{
    use RefreshDatabase;

    private User $student;
    private LabSession $session;

    protected function setUp(): void
    {
        parent::setUp();

        $instructor = User::create(['name' => 'Prof', 'email' => 'prof@example.com', 'password' => bcrypt('password'), 'role' => 'instructor']);
        $this->student = User::create(['name' => 'Ana', 'email' => 'ana@example.com', 'password' => bcrypt('password'), 'role' => 'student']);
        $class = SchoolClass::create(['name' => 'CS 101', 'code' => 'CS101', 'instructor_id' => $instructor->id]);
        $class->students()->attach([$this->student->id => ['status' => 'enrolled']]);
        $module = Module::create(['class_id' => $class->id, 'title' => 'Module 1', 'content' => 'Content', 'order_index' => 0]);
        $lab = Laboratory::create(['module_id' => $module->id, 'title' => 'Lab', 'description' => 'D', 'time_limit' => 0, 'tasks_definition' => []]);
        $this->session = LabSession::create(['lab_id' => $lab->id, 'user_id' => $this->student->id, 'status' => 'in_progress', 'started_at' => now()]);
    }

    public function test_extension_token_calls_on_web_routes_store_no_session_rows(): void
    {
        config(['session.driver' => 'database']);
        $token = $this->session->issueExtensionToken();

        foreach (range(1, 3) as $i) {
            // The /v1 web routes, which is where the extension's calls land on Vercel
            $this->withHeaders(['X-Session-Token' => $token, 'X-Client' => 'vscode'])
                ->getJson("/v1/sessions/{$this->session->id}")
                ->assertOk();
        }
        $this->withHeaders(['X-Session-Token' => $token])
            ->postJson("/v1/sessions/{$this->session->id}/ping", ['source' => 'vscode'])
            ->assertOk();

        $this->assertSame(0, DB::table('sessions')->count());
    }

    public function test_signed_in_browser_still_gets_a_session(): void
    {
        config(['session.driver' => 'database']);

        $this->get('/login')->assertOk();

        $this->assertSame(1, DB::table('sessions')->count());
    }

    public function test_lab_page_reading_the_session_is_not_a_heartbeat(): void
    {
        $this->session->forceFill(['last_ping_at' => now()->subMinutes(10)])->save();

        $this->actingAs($this->student)->getJson("/v1/sessions/{$this->session->id}")->assertOk();

        $this->assertTrue($this->session->fresh()->last_ping_at->lt(now()->subMinutes(9)));
    }

    /**
     * The page's polling is JavaScript, checked here in the rendered page (no browser in the
     * suite): the VS Code connect wait backs off and gives up, and the group-lab chat and
     * stats loop and the VS Code watch slow down while the tab is hidden.
     */
    public function test_lab_page_polls_with_backoff_and_rests_while_hidden(): void
    {
        $this->session->laboratory->update(['is_group_lab' => true]);

        $html = $this->actingAs($this->student)
            ->get(route('laboratories.show', $this->session->lab_id))
            ->assertOk()
            ->getContent();

        $this->assertStringNotContainsString('setInterval(checkPing', $html);
        $this->assertStringContainsString('vscodePollGiveUpMs: 120000', $html);
        $this->assertStringContainsString('this.vscodePollInterval = setTimeout(checkPing, delay)', $html);
        $this->assertStringContainsString('@click="pollForVsCodePing()"', $html);
        $this->assertStringContainsString("if (document.hidden) return;\n                             this.fetchChats();", $html);
        $this->assertStringContainsString('if (document.hidden && tick % 3 !== 0) return;', $html);
    }

    public function test_extension_reading_the_session_is_a_heartbeat(): void
    {
        $this->session->forceFill(['last_ping_at' => now()->subMinutes(10)])->save();

        $this->withHeaders(['X-Session-Token' => $this->session->issueExtensionToken(), 'X-Client' => 'vscode'])
            ->getJson("/v1/sessions/{$this->session->id}")
            ->assertOk()
            ->assertJsonPath('vscode_connected', true);

        $this->assertTrue($this->session->fresh()->last_ping_at->gt(now()->subMinute()));
    }
}
