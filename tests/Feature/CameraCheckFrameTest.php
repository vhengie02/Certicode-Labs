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
use Tests\TestCase;

/**
 * Faces are counted in the browser, so a hand-made request could claim `face_count: 1` with any
 * picture and pass the pre-lab camera check. The server now rejects pictures the lab page can't
 * have taken, rejects a picture used for an earlier check, and keeps the picture of every pass
 * for the class instructor.
 */
class CameraCheckFrameTest extends TestCase
{
    use RefreshDatabase;

    private const ONE_PIXEL_PNG = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==';

    private User $student;

    private User $instructor;

    private Laboratory $laboratory;

    protected function setUp(): void
    {
        parent::setUp();

        $this->student = User::create(['name' => 'Student', 'email' => 'student@example.com', 'password' => bcrypt('password'), 'role' => 'student']);
        $this->instructor = User::create(['name' => 'Prof', 'email' => 'prof@example.com', 'password' => bcrypt('password'), 'role' => 'instructor']);
        $class = SchoolClass::create(['name' => 'Java 101', 'code' => 'JAVA-101', 'instructor_id' => $this->instructor->id]);
        $class->students()->attach($this->student->id, ['status' => 'enrolled']);
        $module = Module::create(['class_id' => $class->id, 'title' => 'Module', 'content' => 'Content']);
        $this->laboratory = $this->lab($module, 'Loops');
    }

    private function lab(Module $module, string $title): Laboratory
    {
        return Laboratory::create(['module_id' => $module->id, 'title' => $title, 'description' => 'Write a loop.', 'time_limit' => 45, 'is_group_lab' => false, 'tasks_definition' => []]);
    }

    private function check(string $image, array $extra = [], ?Laboratory $lab = null)
    {
        $lab ??= $this->laboratory;

        return $this->actingAs($this->student)->postJson("/v1/labs/{$lab->id}/verify-camera", $extra + [
            'status' => 'granted',
            'face_count' => 1,
            'image_base64' => $image,
        ]);
    }

    private function start(?Laboratory $lab = null)
    {
        $lab ??= $this->laboratory;

        return $this->actingAs($this->student)->postJson(route('laboratories.start', $lab->id));
    }

    private function prelabLog(): ?TelemetryLog
    {
        return TelemetryLog::where('event_type', 'webcam_prelab_verification')->latest('id')->first();
    }

    /** A JPEG with an Exif block, as a phone or camera photo has and a canvas picture never does. */
    private function photoWithExif(): string
    {
        $bytes = file_get_contents(base_path('tests/fixtures/camera/camera-frame.jpg'));
        $exif = "Exif\0\0".str_repeat("\0", 16);
        $segment = "\xFF\xE1".pack('n', strlen($exif) + 2).$exif;

        return 'data:image/jpeg;base64,'.base64_encode(substr($bytes, 0, 2).$segment.substr($bytes, 2));
    }

    public function test_a_hand_made_pass_with_a_one_pixel_picture_does_not_open_the_lab(): void
    {
        $this->check(self::ONE_PIXEL_PNG)
            ->assertStatus(422)
            ->assertJson(['error' => 'invalid_camera_frame', 'attempts_used' => 1]);

        $this->start()->assertStatus(403)->assertJson(['error' => 'camera_not_verified']);
        $this->assertSame(0, LabSession::count());
    }

    public function test_a_blank_picture_does_not_pass(): void
    {
        $this->check($this->cameraFrame('blank-camera-frame'))
            ->assertStatus(422)
            ->assertJson(['error' => 'invalid_camera_frame']);

        $this->start()->assertStatus(403);
    }

    public function test_a_picture_smaller_than_any_webcam_does_not_pass(): void
    {
        $this->check($this->cameraFrame('small-camera-frame'))
            ->assertStatus(422)
            ->assertJson(['error' => 'invalid_camera_frame']);
    }

    public function test_a_photo_with_camera_metadata_does_not_pass(): void
    {
        $this->check($this->photoWithExif())
            ->assertStatus(422)
            ->assertJson(['error' => 'invalid_camera_frame']);
    }

    public function test_a_face_count_other_than_one_still_fails_with_a_good_picture(): void
    {
        $this->actingAs($this->student)->postJson("/v1/labs/{$this->laboratory->id}/verify-camera", [
            'status' => 'granted',
            'image_base64' => $this->cameraFrame(),
        ])->assertStatus(422)->assertJson(['error' => 'no_face_detected']);

        $this->check($this->cameraFrame(), ['face_count' => 2])
            ->assertStatus(422)
            ->assertJson(['error' => 'multiple_faces']);
    }

    public function test_three_hand_made_passes_lock_the_lab(): void
    {
        $this->check(self::ONE_PIXEL_PNG)->assertStatus(422);
        $this->check(self::ONE_PIXEL_PNG)->assertStatus(422);
        $this->check(self::ONE_PIXEL_PNG)->assertStatus(423)->assertJson(['error' => 'camera_check_blocked']);

        $this->check($this->cameraFrame())->assertStatus(423);
    }

    public function test_a_lockout_from_hand_made_passes_flags_the_open_attempt_for_what_it_was(): void
    {
        $session = LabSession::create(['lab_id' => $this->laboratory->id, 'user_id' => $this->student->id, 'status' => 'in_progress', 'started_at' => now()]);

        $this->check(self::ONE_PIXEL_PNG)->assertStatus(422);
        $this->check(self::ONE_PIXEL_PNG)->assertStatus(422);
        $this->check(self::ONE_PIXEL_PNG)->assertStatus(423);

        $flag = Anomaly::where('lab_session_id', $session->id)->sole();
        $this->assertSame('camera_check_invalid', $flag->type);
        $this->assertSame('high', $flag->severity);
        $this->assertStringContainsString('not one the lab page takes from a camera', $flag->description);
    }

    public function test_a_real_pass_opens_the_lab_and_keeps_its_picture_on_the_attempt(): void
    {
        $this->check($this->cameraFrame())->assertOk()->assertJson(['verified' => true]);

        $this->start()->assertOk()->assertJsonStructure(['session_id', 'vscode_url']);

        $payload = $this->prelabLog()->payload;
        $this->assertSame(1, $payload['face_count']);
        $this->assertFalse($payload['low_light']);
        $this->assertTrue($payload['has_picture']);
        $this->assertSame($this->cameraFrame(), $payload['image_base64']);
        $this->assertSame(hash('sha256', file_get_contents(base_path('tests/fixtures/camera/camera-frame.jpg'))), $payload['frame_sha256']);
        $this->assertSame(0, Anomaly::count());

        // The picture moved onto the attempt; the login session no longer carries it
        $this->assertArrayNotHasKey('frame', session("camera_verified_lab_{$this->laboratory->id}"));
    }

    public function test_a_low_light_pass_still_opens_the_lab_with_its_picture(): void
    {
        $this->check($this->cameraFrame('dark-camera-frame'), ['low_light' => true])->assertOk();

        $this->start()->assertOk();

        $payload = $this->prelabLog()->payload;
        $this->assertTrue($payload['low_light']);
        $this->assertTrue($payload['has_picture']);
        $this->assertSame(0, Anomaly::count());
    }

    public function test_a_nearly_blank_pass_opens_the_lab_but_flags_it_with_the_picture(): void
    {
        $this->check($this->cameraFrame('faint-camera-frame'), ['low_light' => true])->assertOk();

        $this->start()->assertOk();

        $flag = Anomaly::sole();
        $this->assertSame('camera_check_unusual', $flag->type);
        $this->assertSame('low', $flag->severity);
        $this->assertSame(Anomaly::DB_SNAPSHOT, $flag->image_path);
        $this->assertSame($this->cameraFrame('faint-camera-frame'), $flag->metadata['image_base64']);
        $this->assertSame(['nearly_blank'], $flag->metadata['frame_notes']);
        $this->assertSame(['nearly_blank'], $this->prelabLog()->payload['frame_notes']);
    }

    public function test_the_api_start_route_also_keeps_the_picture(): void
    {
        $this->check($this->cameraFrame())->assertOk();

        $this->actingAs($this->student)->postJson("/v1/labs/{$this->laboratory->id}/start")->assertOk();

        $this->assertTrue($this->prelabLog()->payload['has_picture']);
    }

    public function test_a_picture_from_an_earlier_check_cannot_be_used_again(): void
    {
        $this->check($this->cameraFrame())->assertOk();
        $this->start()->assertOk();

        $otherLab = $this->lab($this->laboratory->module, 'Arrays');
        $this->check($this->cameraFrame(), [], $otherLab)
            ->assertStatus(422)
            ->assertJson(['error' => 'reused_camera_frame', 'attempts_used' => 1]);

        $this->start($otherLab)->assertStatus(403);

        // A fresh picture still passes
        $this->check($this->cameraFrame('dark-camera-frame'), [], $otherLab)->assertOk();
    }

    public function test_the_check_for_an_open_attempt_needs_a_real_picture_too(): void
    {
        $session = LabSession::create(['lab_id' => $this->laboratory->id, 'user_id' => $this->student->id, 'status' => 'in_progress', 'started_at' => now()]);
        $url = "/v1/sessions/{$session->id}/verify-camera";

        $this->actingAs($this->student)->postJson($url, ['status' => 'granted', 'face_count' => 1, 'image_base64' => self::ONE_PIXEL_PNG])
            ->assertStatus(422)
            ->assertJson(['error' => 'invalid_camera_frame']);
        $this->assertNull($this->prelabLog());

        $this->actingAs($this->student)->postJson($url, ['status' => 'granted', 'face_count' => 1, 'low_light' => true, 'image_base64' => $this->cameraFrame('faint-camera-frame')])
            ->assertOk();

        $payload = $this->prelabLog()->payload;
        $this->assertTrue($payload['has_picture']);
        $this->assertTrue($payload['low_light']);
        $this->assertSame('camera_check_unusual', Anomaly::sole()->type);
    }

    public function test_only_the_class_instructor_can_see_the_picture_of_a_pass(): void
    {
        $this->check($this->cameraFrame())->assertOk();
        $this->start()->assertOk();
        $log = $this->prelabLog();
        $url = route('camera-checks.picture', $log->id);

        $otherInstructor = User::create(['name' => 'Other', 'email' => 'other@example.com', 'password' => bcrypt('password'), 'role' => 'instructor']);
        $this->actingAs($this->student)->get($url)->assertForbidden();
        $this->actingAs($otherInstructor)->get($url)->assertForbidden();

        $response = $this->actingAs($this->instructor)->get($url)
            ->assertOk()
            ->assertHeader('Content-Type', 'image/jpeg')
            ->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->assertSame(file_get_contents(base_path('tests/fixtures/camera/camera-frame.jpg')), $response->getContent());

        $this->actingAs($this->instructor)
            ->get(route('sessions.telemetry-timeline', $log->lab_session_id))
            ->assertOk()
            ->assertSee($url, false);
    }

    public function test_the_picture_route_is_only_for_camera_checks_with_a_picture(): void
    {
        $session = LabSession::create(['lab_id' => $this->laboratory->id, 'user_id' => $this->student->id, 'status' => 'in_progress', 'started_at' => now()]);
        $ping = TelemetryLog::create(['lab_session_id' => $session->id, 'event_type' => 'tab_switch', 'payload' => ['image_base64' => $this->cameraFrame()]]);
        $oldCheck = TelemetryLog::create(['lab_session_id' => $session->id, 'event_type' => 'webcam_prelab_verification', 'payload' => ['face_count' => 1]]);

        $this->actingAs($this->instructor)->get(route('camera-checks.picture', $ping->id))->assertNotFound();
        $this->actingAs($this->instructor)->get(route('camera-checks.picture', $oldCheck->id))->assertNotFound();
    }

    public function test_the_timeline_does_not_load_the_picture_itself(): void
    {
        $this->check($this->cameraFrame())->assertOk();
        $this->start()->assertOk();
        $log = $this->prelabLog();

        $logs = $this->actingAs($this->instructor)
            ->get(route('sessions.telemetry-timeline', $log->lab_session_id))
            ->viewData('logs');

        $check = $logs->firstWhere('event_type', 'webcam_prelab_verification');
        $this->assertArrayNotHasKey('image_base64', $check->payload);
        $this->assertTrue($check->payload['has_picture']);
    }

    public function test_old_camera_check_pictures_are_removed_and_recent_ones_kept(): void
    {
        $session = LabSession::create(['lab_id' => $this->laboratory->id, 'user_id' => $this->student->id, 'status' => 'in_progress', 'started_at' => now()]);
        $payload = ['face_count' => 1, 'low_light' => false, 'has_picture' => true, 'image_base64' => $this->cameraFrame(), 'frame_sha256' => 'abc'];
        $old = TelemetryLog::create(['lab_session_id' => $session->id, 'event_type' => 'webcam_prelab_verification', 'payload' => $payload]);
        $recent = TelemetryLog::create(['lab_session_id' => $session->id, 'event_type' => 'webcam_prelab_verification', 'payload' => $payload]);
        DB::table('telemetry_logs')->where('id', $old->id)->update(['created_at' => now()->subDays(config('app.snapshot_retention_days') + 5)]);

        $this->getJson('/cron/tick')->assertOk();

        $oldPayload = $old->fresh()->payload;
        $this->assertArrayNotHasKey('image_base64', $oldPayload);
        $this->assertFalse($oldPayload['has_picture']);
        $this->assertTrue($oldPayload['picture_expired']);
        $this->assertSame('abc', $oldPayload['frame_sha256']);
        $this->assertSame(1, $oldPayload['face_count']);

        $this->assertSame($this->cameraFrame(), $recent->fresh()->payload['image_base64']);
        $this->assertTrue($recent->fresh()->payload['has_picture']);
    }

    public function test_the_account_export_leaves_out_the_picture(): void
    {
        $this->check($this->cameraFrame())->assertOk();
        $this->start()->assertOk();

        $export = $this->actingAs($this->student)->get(route('settings.export'))->assertOk()->streamedContent();

        $this->assertStringContainsString('webcam_prelab_verification', $export);
        $this->assertStringContainsString('"has_picture":true', $export);
        $this->assertStringNotContainsString('image_base64', $export);
    }
}
