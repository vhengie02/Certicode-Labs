<?php

namespace Tests\Feature;

use App\Models\LabSession;
use App\Models\Laboratory;
use App\Models\Module;
use App\Models\SchoolClass;
use App\Models\TelemetryLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Regression tests for the pre-lab camera gate: it trusted a camera_verified flag sent by the
 * browser, and a low-light pass was recorded as a clean one-face verification.
 */
class CameraGateTest extends TestCase
{
    use RefreshDatabase;

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

    public function test_a_camera_check_recorded_by_the_server_opens_the_lab(): void
    {
        $this->actingAs($this->student)
            ->postJson("/v1/labs/{$this->laboratory->id}/verify-camera", ['status' => 'granted', 'face_count' => 1])
            ->assertOk();

        $this->start()->assertOk()->assertJsonStructure(['session_id', 'vscode_url']);

        $payload = $this->prelabLog()->payload;
        $this->assertSame(1, $payload['face_count']);
        $this->assertFalse($payload['low_light']);
    }

    public function test_a_low_light_pass_is_recorded_as_low_light_not_as_one_face(): void
    {
        $this->actingAs($this->student)
            ->postJson("/v1/labs/{$this->laboratory->id}/verify-camera", ['status' => 'granted', 'face_count' => 1, 'low_light' => true])
            ->assertOk();

        $this->start()->assertOk();

        $payload = $this->prelabLog()->payload;
        $this->assertTrue($payload['low_light']);
        $this->assertNull($payload['face_count']);
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
            ->postJson("/v1/labs/{$this->laboratory->id}/verify-camera", ['status' => 'granted', 'face_count' => 1])
            ->assertOk();

        $this->travel(11)->minutes();

        $this->start()->assertStatus(403);
    }
}
