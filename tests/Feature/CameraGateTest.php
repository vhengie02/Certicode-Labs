<?php

namespace Tests\Feature;

use App\Http\Controllers\LabAttemptController;
use App\Models\Anomaly;
use App\Models\Laboratory;
use App\Models\LabSession;
use App\Models\Module;
use App\Models\SchoolClass;
use App\Models\TelemetryLog;
use App\Models\User;
use App\Notifications\CameraFlagNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * Regression tests for the pre-lab camera gate: it trusted a camera_verified flag sent by the
 * browser, and a low-light button let an empty room pass without a detected face.
 */
class CameraGateTest extends TestCase
{
    use RefreshDatabase;

    private const CAMERA_FRAME = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==';

    private User $student;

    private Laboratory $laboratory;

    protected function setUp(): void
    {
        parent::setUp();

        $this->student = User::create(['name' => 'Student', 'email' => 'student@example.com', 'password' => bcrypt('password'), 'role' => 'student']);
        $instructor = User::create(['name' => 'Prof', 'email' => 'prof@example.com', 'password' => bcrypt('password'), 'role' => 'instructor']);
        $class = SchoolClass::create(['name' => 'Java 101', 'code' => 'JAVA-101', 'instructor_id' => $instructor->id]);
        $class->students()->attach($this->student->id, ['status' => 'enrolled']);
        $module = Module::create(['class_id' => $class->id, 'title' => 'Module', 'content' => 'Content']);
        $this->laboratory = Laboratory::create(['module_id' => $module->id, 'title' => 'Loops', 'description' => 'Write a loop.', 'time_limit' => 45, 'is_group_lab' => false, 'tasks_definition' => []]);
    }

    private function start()
    {
        return $this->actingAs($this->student)->postJson(route('laboratories.start', $this->laboratory->id), ['camera_verified' => 1]);
    }

    private function prelabLog(): ?TelemetryLog
    {
        return TelemetryLog::where('event_type', 'webcam_prelab_verification')->first();
    }

    public function test_a_camera_verified_flag_from_the_browser_does_not_open_the_lab(): void
    {
        $this->start()->assertStatus(403)->assertJson(['error' => 'camera_not_verified']);

        $this->assertSame(0, LabSession::count());
    }

    public function test_the_api_start_route_needs_the_camera_check_too(): void
    {
        $this->actingAs($this->student)
            ->postJson("/v1/labs/{$this->laboratory->id}/start")
            ->assertStatus(403)
            ->assertJson(['error' => 'camera_not_verified']);

        $this->assertSame(0, LabSession::count());

        $this->actingAs($this->student)
            ->withCameraCheck($this->laboratory->id)
            ->postJson("/v1/labs/{$this->laboratory->id}/start")
            ->assertOk();

        $this->assertSame(1, LabSession::count());
    }

    public function test_an_instructor_can_try_a_lab_through_the_api_without_the_camera_check(): void
    {
        $instructor = User::where('role', 'instructor')->first();

        $this->actingAs($instructor)
            ->postJson("/v1/labs/{$this->laboratory->id}/start")
            ->assertOk();
    }

    public function test_a_camera_check_recorded_by_the_server_opens_the_lab(): void
    {
        $this->actingAs($this->student)
            ->postJson("/v1/labs/{$this->laboratory->id}/verify-camera", ['status' => 'granted', 'face_count' => 1, 'image_base64' => self::CAMERA_FRAME])
            ->assertOk();

        $this->start()->assertOk()->assertJsonStructure(['session_id', 'vscode_url']);

        $payload = $this->prelabLog()->payload;
        $this->assertSame(1, $payload['face_count']);
        $this->assertFalse($payload['low_light']);
    }

    public function test_a_check_in_a_dim_room_is_recorded_as_low_light_with_its_face(): void
    {
        $this->actingAs($this->student)
            ->postJson("/v1/labs/{$this->laboratory->id}/verify-camera", ['status' => 'granted', 'face_count' => 1, 'low_light' => true, 'image_base64' => self::CAMERA_FRAME])
            ->assertOk();

        $this->start()->assertOk();

        $payload = $this->prelabLog()->payload;
        $this->assertTrue($payload['low_light']);
        $this->assertSame(1, $payload['face_count']);
    }

    public function test_the_lab_page_has_no_way_past_the_gate_without_a_face(): void
    {
        $this->actingAs($this->student)
            ->get(route('laboratories.show', $this->laboratory->id))
            ->assertOk()
            ->assertSee('Pre-Lab Camera Presence Verification')
            ->assertDontSee('Continue in low-light mode')
            ->assertDontSee('acceptLowLightPresence');
    }

    public function test_a_low_light_check_that_found_no_face_does_not_open_the_lab(): void
    {
        $this->actingAs($this->student)
            ->postJson("/v1/labs/{$this->laboratory->id}/verify-camera", ['status' => 'granted', 'face_count' => 0, 'low_light' => true, 'image_base64' => self::CAMERA_FRAME])
            ->assertStatus(422)
            ->assertJson(['error' => 'no_face_detected']);

        $this->start()->assertStatus(403);
    }

    public function test_a_failed_camera_check_does_not_open_the_lab(): void
    {
        $this->actingAs($this->student)
            ->postJson("/v1/labs/{$this->laboratory->id}/verify-camera", ['status' => 'granted', 'face_count' => 2])
            ->assertStatus(422);

        $this->start()->assertStatus(403);
        $this->assertSame(0, LabSession::count());
    }

    public function test_an_old_camera_check_does_not_open_the_lab(): void
    {
        $this->actingAs($this->student)
            ->postJson("/v1/labs/{$this->laboratory->id}/verify-camera", ['status' => 'granted', 'face_count' => 1, 'image_base64' => self::CAMERA_FRAME])
            ->assertOk();

        $this->travel(11)->minutes();

        $this->start()->assertStatus(403);
    }

    private function check(int $faceCount, array $extra = [])
    {
        return $this->actingAs($this->student)
            ->postJson("/v1/labs/{$this->laboratory->id}/verify-camera", ['status' => 'granted', 'face_count' => $faceCount, 'image_base64' => self::CAMERA_FRAME] + $extra);
    }

    private function failThreeTimes(): void
    {
        $this->check(0)->assertStatus(422)->assertJson(['attempts_used' => 1]);
        $this->check(2)->assertStatus(422)->assertJson(['attempts_used' => 2]);
        $this->check(0)->assertStatus(423)->assertJson(['error' => 'camera_check_blocked', 'attempts_used' => 3]);
    }

    public function test_three_failed_checks_lock_the_student_out_even_if_a_later_check_passes(): void
    {
        $this->failThreeTimes();

        $this->check(1)->assertStatus(423);
        $this->start()->assertStatus(403);
        $this->assertSame(0, LabSession::count());
    }

    public function test_a_locked_out_student_gets_no_more_attempts_counted(): void
    {
        $this->failThreeTimes();

        $this->check(0)->assertStatus(423)->assertJson(['attempts_used' => 3]);
    }

    public function test_the_lockout_also_covers_the_check_for_an_attempt_already_open(): void
    {
        $session = LabSession::create(['user_id' => $this->student->id, 'lab_id' => $this->laboratory->id, 'status' => 'in_progress']);
        $this->failThreeTimes();

        $this->actingAs($this->student)
            ->postJson("/v1/sessions/{$session->id}/verify-camera", ['status' => 'granted', 'face_count' => 1, 'image_base64' => self::CAMERA_FRAME])
            ->assertStatus(423);
    }

    public function test_a_lockout_also_cancels_an_earlier_pass(): void
    {
        $this->check(1)->assertOk();
        $this->failThreeTimes();

        $this->start()->assertStatus(403);
    }

    public function test_the_lockout_ends_after_fifteen_minutes(): void
    {
        $this->failThreeTimes();

        $this->travel(14)->minutes();
        $this->check(1)->assertStatus(423)->assertJsonFragment(['message' => 'Too many failed camera checks. Contact your instructor. You can try again in 1 minute.']);

        $this->travel(2)->minutes();
        $this->check(1)->assertOk();
        $this->start()->assertOk();
    }

    public function test_a_passed_check_clears_the_failed_attempts(): void
    {
        $this->check(0)->assertStatus(422);
        $this->check(0)->assertStatus(422);
        $this->check(1)->assertOk();

        $this->check(0)->assertStatus(422)->assertJson(['attempts_used' => 1]);
    }

    public function test_attempts_are_counted_per_lab(): void
    {
        $this->failThreeTimes();

        $otherLab = Laboratory::create(['module_id' => $this->laboratory->module_id, 'title' => 'Arrays', 'description' => 'Sort.', 'time_limit' => 45, 'is_group_lab' => false, 'tasks_definition' => []]);

        $this->actingAs($this->student)
            ->postJson("/v1/labs/{$otherLab->id}/verify-camera", ['status' => 'granted', 'face_count' => 1, 'image_base64' => self::CAMERA_FRAME])
            ->assertOk();
    }

    public function test_a_lockout_with_an_open_attempt_flags_it_with_the_picture(): void
    {
        $session = LabSession::create(['user_id' => $this->student->id, 'lab_id' => $this->laboratory->id, 'status' => 'in_progress']);

        $this->failThreeTimes();

        $flag = Anomaly::where('lab_session_id', $session->id)->sole();
        $this->assertSame('no_face', $flag->type);
        $this->assertSame('high', $flag->severity);
        $this->assertStringContainsString('after 3 attempts', $flag->description);
        $this->assertSame(Anomaly::DB_SNAPSHOT, $flag->image_path);
        $this->assertTrue($flag->metadata['pre_lab']);
    }

    public function test_a_lockout_with_no_attempt_notifies_the_instructor(): void
    {
        Notification::fake();

        $this->failThreeTimes();

        $this->assertSame(0, Anomaly::count());
        Notification::assertSentTo($this->laboratory->module->schoolClass->instructor, CameraFlagNotification::class);
    }

    private function labPage()
    {
        return $this->actingAs($this->student)->get(route('laboratories.show', $this->laboratory->id))->assertOk();
    }

    public function test_the_lab_page_uses_the_server_attempt_limit(): void
    {
        $this->labPage()->assertSee('maxAttempts: '.LabAttemptController::CAMERA_MAX_ATTEMPTS, false);
    }

    public function test_reloading_the_lab_page_keeps_the_attempt_count(): void
    {
        $this->check(0)->assertStatus(422);
        $this->check(0)->assertStatus(422);

        $this->labPage()->assertSee('attempts: 2,', false);
    }

    public function test_the_attempt_count_starts_over_after_the_lockout_ends(): void
    {
        $this->failThreeTimes();
        $this->labPage()->assertSee('attempts: 3,', false);

        $this->travel(16)->minutes();

        $this->labPage()->assertSee('attempts: 0,', false);
    }
}
