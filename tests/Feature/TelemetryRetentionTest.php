<?php

namespace Tests\Feature;

use App\Models\Anomaly;
use App\Models\Laboratory;
use App\Models\LabSession;
use App\Models\Module;
use App\Models\SchoolClass;
use App\Models\TelemetryLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Telemetry and webcam snapshots must not grow without bound: heartbeats are pruned,
 * old snapshots are dropped from their flags, and stored payloads are bounded.
 */
class TelemetryRetentionTest extends TestCase
{
    use RefreshDatabase;

    private const SNAPSHOT = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==';

    private User $instructor;
    private LabSession $session;

    protected function setUp(): void
    {
        parent::setUp();

        $this->instructor = User::create(['name' => 'Prof', 'email' => 'prof@example.com', 'password' => bcrypt('password'), 'role' => 'instructor']);
        $student = User::create(['name' => 'Ana', 'email' => 'ana@example.com', 'password' => bcrypt('password'), 'role' => 'student']);
        $class = SchoolClass::create(['name' => 'CS 101', 'code' => 'CS101', 'instructor_id' => $this->instructor->id]);
        $class->students()->attach([$student->id => ['status' => 'enrolled']]);
        $module = Module::create(['class_id' => $class->id, 'title' => 'Module 1', 'content' => 'Content', 'order_index' => 0]);
        $lab = Laboratory::create(['module_id' => $module->id, 'title' => 'Lab', 'description' => 'D', 'time_limit' => 0, 'tasks_definition' => []]);
        $this->session = LabSession::create(['lab_id' => $lab->id, 'user_id' => $student->id, 'status' => 'in_progress', 'started_at' => now()]);
    }

    private function log(string $eventType, int $daysAgo): TelemetryLog
    {
        $log = TelemetryLog::create(['lab_session_id' => $this->session->id, 'event_type' => $eventType, 'payload' => []]);
        DB::table('telemetry_logs')->where('id', $log->id)->update(['created_at' => now()->subDays($daysAgo)]);

        return $log;
    }

    private function snapshotFlag(int $daysAgo, bool $resolved = false): Anomaly
    {
        $anomaly = Anomaly::create([
            'lab_session_id' => $this->session->id,
            'type' => 'no_face',
            'severity' => 'high',
            'description' => 'No face',
            'resolved' => $resolved,
            'image_path' => Anomaly::DB_SNAPSHOT,
            'metadata' => ['image_base64' => self::SNAPSHOT, 'low_light' => false],
        ]);
        DB::table('anomalies')->where('id', $anomaly->id)->update(['created_at' => now()->subDays($daysAgo)]);

        return $anomaly;
    }

    public function test_cron_prunes_old_heartbeats_and_keeps_history(): void
    {
        $oldPing = $this->log('vscode_ping', 30);
        $oldWpm = $this->log('wpm_update', 30);
        $recentPing = $this->log('vscode_ping', 1);
        $oldSwitch = $this->log('tab_switch', 30);

        $this->getJson('/cron/tick')->assertOk();

        $this->assertModelMissing($oldPing);
        $this->assertModelMissing($oldWpm);
        $this->assertModelExists($recentPing);
        $this->assertModelExists($oldSwitch);
    }

    public function test_cron_removes_old_snapshots_but_keeps_the_open_flag(): void
    {
        $old = $this->snapshotFlag(45);
        $recent = $this->snapshotFlag(2);

        $this->getJson('/cron/tick')->assertOk();

        $old->refresh();
        $this->assertNull($old->image_path);
        $this->assertArrayNotHasKey('image_base64', $old->metadata);
        $this->assertTrue($old->metadata['snapshot_expired']);
        $this->assertFalse($old->metadata['low_light']);

        $this->assertSame(Anomaly::DB_SNAPSHOT, $recent->fresh()->image_path);
        $this->assertSame(self::SNAPSHOT, $recent->fresh()->metadata['image_base64']);
    }

    public function test_repeated_camera_flags_store_the_picture_at_most_every_two_minutes(): void
    {
        $send = fn () => $this->withHeaders(['X-Session-Token' => $this->session->issueExtensionToken()])
            ->postJson("/api/v1/sessions/{$this->session->id}/telemetry", [
                'event_type' => 'camera_absence',
                'payload' => ['image_base64' => self::SNAPSHOT],
            ])->assertOk();

        $send();
        $this->travel(35)->seconds();
        $send();
        $this->travel(2)->minutes();
        $send();

        $flags = Anomaly::where('lab_session_id', $this->session->id)->orderBy('id')->get();
        $this->assertCount(3, $flags);
        $this->assertSame([Anomaly::DB_SNAPSHOT, null, Anomaly::DB_SNAPSHOT], $flags->pluck('image_path')->all());
        $this->assertArrayNotHasKey('image_base64', $flags[1]->metadata);
    }

    public function test_oversized_telemetry_payload_is_cut_down(): void
    {
        $this->withHeaders(['X-Session-Token' => $this->session->issueExtensionToken()])
            ->postJson("/api/v1/sessions/{$this->session->id}/telemetry", [
                'event_type' => 'tab_switch',
                'payload' => ['event' => 'blur', 'junk' => array_fill(0, 2000, str_repeat('x', 50))],
            ])->assertOk();

        $log = TelemetryLog::where('lab_session_id', $this->session->id)->where('event_type', 'tab_switch')->firstOrFail();
        $this->assertLessThanOrEqual(8192, strlen(json_encode($log->payload)));
        $this->assertSame('blur', $log->payload['event']);
        $this->assertTrue($log->payload['truncated']);
    }

    public function test_session_timeline_does_not_load_snapshot_images(): void
    {
        $this->snapshotFlag(1);

        $response = $this->actingAs($this->instructor)->get(route('sessions.telemetry-timeline', $this->session->id));

        $response->assertOk();
        $anomaly = $response->viewData('anomalies')->first();
        $this->assertArrayNotHasKey('image_base64', $anomaly->metadata);
        $this->assertNotNull($anomaly->snapshot_url);
    }

    public function test_telemetry_lookups_have_a_session_and_event_index(): void
    {
        $indexes = collect(Schema::getIndexes('telemetry_logs'))->pluck('columns')->all();

        $this->assertContains(['lab_session_id', 'event_type', 'created_at'], $indexes);
        $this->assertNotContains(['event_type'], $indexes);
        $this->assertContains(['lab_session_id', 'type', 'created_at'], collect(Schema::getIndexes('anomalies'))->pluck('columns')->all());
    }
}
