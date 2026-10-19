<?php

namespace Tests\Feature;

use App\Models\Anomaly;
use App\Models\Laboratory;
use App\Models\LabSession;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Regression tests for false "excessive tab switch" flags: the lab page reported going out of
 * view (and back) as tab switches while students coded in VS Code as instructed, and each
 * further switch in the 2-minute window added another flag.
 */
class LabPageFocusFlagsTest extends TestCase
{
    use RefreshDatabase;

    private LabSession $session;

    protected function setUp(): void
    {
        parent::setUp();

        $student = User::create(['name' => 'Student', 'email' => 'student@example.com', 'password' => bcrypt('password'), 'role' => 'student']);
        $lab = Laboratory::create(['title' => 'Loops', 'description' => 'Write a loop.', 'time_limit' => 45, 'is_group_lab' => false, 'tasks_definition' => []]);
        $this->session = LabSession::create(['lab_id' => $lab->id, 'user_id' => $student->id, 'status' => 'in_progress', 'started_at' => now()]);
        $this->actingAs($student);
    }

    private function telemetry(string $type, array $payload = []): void
    {
        $this->postJson("/v1/sessions/{$this->session->id}/telemetry", ['event_type' => $type, 'payload' => $payload])->assertOk();
    }

    public function test_looking_back_at_the_lab_page_once_raises_no_flag(): void
    {
        // VS Code loses focus, the lab page shows, then VS Code covers it again
        $this->telemetry('focus_lost');
        $this->telemetry('tab_switch', ['event' => 'tab_visible']);
        $this->telemetry('tab_switch', ['event' => 'tab_hidden']);
        $this->telemetry('lab_page_hidden');

        $this->assertSame(0, Anomaly::where('lab_session_id', $this->session->id)->count());
    }

    public function test_frequent_switching_raises_one_flag_that_grows(): void
    {
        for ($i = 0; $i < 7; $i++) {
            $this->telemetry('focus_lost');
        }

        $flags = Anomaly::where('lab_session_id', $this->session->id)->where('type', 'excessive_focus_loss')->get();
        $this->assertCount(1, $flags);
        $this->assertSame('high', $flags[0]->severity);
        $this->assertStringContainsString('7 times', $flags[0]->description);
    }
}
