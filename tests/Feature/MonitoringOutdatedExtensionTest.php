<?php

namespace Tests\Feature;

use App\Models\Laboratory;
use App\Models\LabSession;
use App\Models\Module;
use App\Models\SchoolClass;
use App\Models\TelemetryLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The lab monitoring roster marks students whose VS Code extension is below the minimum build,
 * judged from the last ping of each in-progress session.
 */
class MonitoringOutdatedExtensionTest extends TestCase
{
    use RefreshDatabase;

    private User $instructor;

    private SchoolClass $class;

    private Laboratory $lab;

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.vscode_extension.min_version' => '1.7.0']);

        $this->instructor = User::create(['name' => 'Prof', 'email' => 'prof@example.com', 'password' => bcrypt('password'), 'role' => 'instructor']);
        $this->class = SchoolClass::create(['name' => 'CS 101', 'code' => 'CS101', 'instructor_id' => $this->instructor->id]);
        $module = Module::create(['class_id' => $this->class->id, 'title' => 'Module 1', 'content' => 'Content', 'order_index' => 0]);
        $this->lab = Laboratory::create([
            'module_id' => $module->id,
            'title' => 'Tasks',
            'description' => 'Build a task manager',
            'time_limit' => 0,
            'max_attempts' => 0,
            'tasks_definition' => [['id' => 1, 'task' => 'Add tasks']],
        ]);
    }

    private function sessionPingingFrom(string $name, ?string $version, string $status = 'in_progress'): LabSession
    {
        $user = User::create(['name' => $name, 'email' => strtolower($name).'@example.com', 'password' => bcrypt('password'), 'role' => 'student']);
        $this->class->students()->attach([$user->id => ['status' => 'enrolled']]);
        $session = LabSession::create([
            'lab_id' => $this->lab->id,
            'user_id' => $user->id,
            'status' => $status,
            'started_at' => now()->subMinutes(5),
            'last_ping_at' => now(),
        ]);
        TelemetryLog::create([
            'lab_session_id' => $session->id,
            'event_type' => 'vscode_ping',
            'payload' => $version === null ? ['source' => 'vscode'] : ['source' => 'vscode', 'extension_version' => $version],
        ]);

        return $session;
    }

    private function panel()
    {
        return $this->actingAs($this->instructor)
            ->get(route('classes.telemetry', ['class_id' => $this->class->id, 'lab' => $this->lab->id]))
            ->assertOk();
    }

    public function test_roster_marks_only_outdated_in_progress_extensions(): void
    {
        $old = $this->sessionPingingFrom('Olivia', '1.6.0');
        $unreported = $this->sessionPingingFrom('Uma', '');
        $current = $this->sessionPingingFrom('Carl', '1.7.1');
        $legacyPing = $this->sessionPingingFrom('Lee', null);
        $finished = $this->sessionPingingFrom('Fay', '1.5.0', 'completed');

        $outdated = $this->panel()->viewData('outdatedExtensions');

        $this->assertSame([$old->id => '1.6.0', $unreported->id => ''], $outdated);
        $this->assertArrayNotHasKey($current->id, $outdated);
        $this->assertArrayNotHasKey($legacyPing->id, $outdated);
        $this->assertArrayNotHasKey($finished->id, $outdated);
    }

    public function test_only_the_latest_ping_counts(): void
    {
        $session = $this->sessionPingingFrom('Olivia', '1.6.0');
        // The student updated the extension mid-lab
        TelemetryLog::create([
            'lab_session_id' => $session->id,
            'event_type' => 'vscode_ping',
            'payload' => ['source' => 'vscode', 'extension_version' => '1.7.1'],
        ]);

        $this->assertSame([], $this->panel()->viewData('outdatedExtensions'));
    }

    public function test_badge_shows_the_reported_build(): void
    {
        $this->sessionPingingFrom('Olivia', '1.6.0');

        $this->panel()
            ->assertSee('Old ext')
            ->assertSee('VS Code extension v1.6.0 is older than the minimum, v1.7.0');
    }
}
