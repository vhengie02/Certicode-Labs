<?php

namespace Tests\Feature;

use App\Models\Anomaly;
use App\Models\Certificate;
use App\Models\Laboratory;
use App\Models\LabSession;
use App\Models\LabSessionChat;
use App\Models\Module;
use App\Models\SchoolClass;
use App\Models\TelemetryLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TelemetryAndProctoringTest extends TestCase
{
    use RefreshDatabase;

    protected User $instructor;
    protected User $student1;
    protected User $student2;
    protected SchoolClass $class;
    protected Module $module;
    protected Laboratory $laboratory;
    protected LabSession $session1;
    protected LabSession $session2;

    protected function setUp(): void
    {
        parent::setUp();

        $this->instructor = User::create([
            'name' => 'Professor Smith',
            'email' => 'prof@example.com',
            'password' => bcrypt('password'),
            'role' => 'instructor',
        ]);

        $this->student1 = User::create([
            'name' => 'Alice Walker',
            'email' => 'alice@example.com',
            'password' => bcrypt('password'),
            'role' => 'student',
        ]);

        $this->student2 = User::create([
            'name' => 'Bob Builder',
            'email' => 'bob@example.com',
            'password' => bcrypt('password'),
            'role' => 'student',
        ]);

        $this->class = SchoolClass::create([
            'name' => 'CS 101 Systems',
            'code' => 'CS101-JOIN',
            'instructor_id' => $this->instructor->id,
            'description' => 'Course description',
            'passing_threshold' => 75,
        ]);

        $this->class->students()->attach([
            $this->student1->id => ['status' => 'enrolled'],
            $this->student2->id => ['status' => 'enrolled'],
        ]);

        $this->module = Module::create([
            'class_id' => $this->class->id,
            'title' => 'Module 1: Getting Started',
            'content' => 'Module content',
            'order_index' => 0,
        ]);

        $this->laboratory = Laboratory::create([
            'module_id' => $this->module->id,
            'title' => 'Lab 1: Binary Trees',
            'description' => 'Implement tree traversal',
            'time_limit' => 45,
            'is_group_lab' => false,
            'tasks_definition' => [
                ['id' => 1, 'task' => 'Define TreeNode'],
                ['id' => 2, 'task' => 'Implement InOrder'],
            ],
        ]);

        $this->session1 = LabSession::create([
            'lab_id' => $this->laboratory->id,
            'user_id' => $this->student1->id,
            'status' => 'in_progress',
            'started_at' => now()->subMinutes(10),
            'completed_tasks' => [1],
        ]);

        $this->session2 = LabSession::create([
            'lab_id' => $this->laboratory->id,
            'user_id' => $this->student2->id,
            'status' => 'in_progress',
            'started_at' => now()->subMinutes(5),
            'completed_tasks' => [1, 2],
        ]);
    }

    public function test_focus_lost_telemetry_logs_and_triggers_excessive_anomaly()
    {
        // Send focus_lost 3 times
        for ($i = 0; $i < 3; $i++) {
            $response = $this->postJson("/api/v1/sessions/{$this->session1->id}/telemetry", [
                'event_type' => 'focus_lost',
                'payload' => ['timestamp' => now()->toISOString()],
            ]);
            $response->assertStatus(200);
        }

        $this->session1->refresh();
        $this->assertEquals(3, $this->session1->focus_lost_count);

        // Anomaly should be created for excessive focus loss
        $this->assertDatabaseHas('anomalies', [
            'lab_session_id' => $this->session1->id,
            'type' => 'excessive_focus_loss',
        ]);
    }

    public function test_wpm_update_telemetry()
    {
        $response = $this->postJson("/api/v1/sessions/{$this->session1->id}/telemetry", [
            'event_type' => 'wpm_update',
            'payload' => [
                'wpm' => 64,
                'keystroke_count' => 320,
            ],
        ]);

        $response->assertStatus(200);
        $this->session1->refresh();
        $this->assertEquals(64, $this->session1->wpm);
        $this->assertEquals(320, $this->session1->keystroke_count);
    }

    public function test_paste_anomaly_telemetry_creates_record_silently()
    {
        $response = $this->postJson("/api/v1/sessions/{$this->session1->id}/telemetry", [
            'event_type' => 'paste_anomaly',
            'payload' => [
                'pasted_length' => 150,
                'snippet' => 'public class Tree { void insert() { ... } }',
                'file' => 'Tree.java',
                'wpm' => 45,
            ],
        ]);

        $response->assertStatus(200);
        $this->session1->refresh();
        $this->assertEquals(1, $this->session1->paste_anomaly_count);

        $this->assertDatabaseHas('anomalies', [
            'lab_session_id' => $this->session1->id,
            'type' => 'paste_anomaly',
            'severity' => 'high',
        ]);
    }

    public function test_live_leaderboard_ranks_by_tasks_descending()
    {
        $response = $this->getJson("/api/v1/sessions/{$this->session1->id}/leaderboard");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'status',
                'leaderboard' => [
                    '*' => ['rank', 'user_id', 'name', 'tasks_completed', 'elapsed_time'],
                ],
            ]);

        $leaderboard = $response->json('leaderboard');
        $this->assertCount(2, $leaderboard);
        // Student 2 has 2 completed tasks, so should be Rank #1
        $this->assertEquals($this->student2->id, $leaderboard[0]['user_id']);
        $this->assertEquals(1, $leaderboard[0]['rank']);
        // Student 1 has 1 completed task, so should be Rank #2
        $this->assertEquals($this->student1->id, $leaderboard[1]['user_id']);
        $this->assertEquals(2, $leaderboard[1]['rank']);
    }

    public function test_instructor_can_end_session_and_purge_chats()
    {
        // Add chat record
        LabSessionChat::create([
            'lab_session_id' => $this->session1->id,
            'user_id' => $this->student1->id,
            'user_name' => 'Alice Walker',
            'message' => 'Hey team!',
        ]);

        $this->actingAs($this->instructor);
        $response = $this->post("/instructor/sessions/{$this->session1->id}/end");
        $response->assertRedirect();

        $this->session1->refresh();
        $this->assertEquals('completed', $this->session1->status);
        $this->assertNotNull($this->session1->closed_at);

        // Chat should be purged
        $this->assertDatabaseMissing('lab_session_chats', [
            'lab_session_id' => $this->session1->id,
        ]);
    }

    public function test_instructor_can_reopen_session()
    {
        $this->session1->update(['status' => 'completed', 'closed_at' => now()]);

        $this->actingAs($this->instructor);
        $response = $this->post("/instructor/sessions/{$this->session1->id}/reopen");
        $response->assertRedirect();

        $this->session1->refresh();
        $this->assertEquals('in_progress', $this->session1->status);
        $this->assertNull($this->session1->closed_at);
    }

    public function test_conclude_class_awards_certificate_to_qualifying_students()
    {
        // Set student2 as completed lab 1 (so 100% progress >= 75% threshold)
        $this->session2->update(['status' => 'completed']);

        $this->actingAs($this->instructor);
        $response = $this->post("/classes/{$this->class->id}/end");
        $response->assertRedirect();

        $this->class->refresh();
        $this->assertEquals('completed', $this->class->status);

        // Student2 should receive a certificate
        $this->assertDatabaseHas('certificates', [
            'user_id' => $this->student2->id,
            'class_id' => $this->class->id,
        ]);
    }

    public function test_instructor_monitoring_panel_renders_and_streams_data()
    {
        $this->actingAs($this->instructor);

        $viewResponse = $this->get("/laboratories/{$this->laboratory->id}/monitoring");
        $viewResponse->assertStatus(200)
            ->assertDontSee('Live Telemetry')
            ->assertSee('Telemetry Monitoring')
            ->assertSee('Alice Walker')
            ->assertSee('Bob Builder');

        $dataResponse = $this->getJson("/laboratories/{$this->laboratory->id}/monitoring/data");
        $dataResponse->assertStatus(200)
            ->assertJsonStructure([
                'status',
                'sessions' => [
                    '*' => ['id', 'user_name', 'wpm', 'tasks_completed', 'anomalies'],
                ],
            ]);
    }

    public function test_prelab_camera_verification_denied_returns_forbidden_and_logs_telemetry()
    {
        $response = $this->postJson("/api/v1/sessions/{$this->session1->id}/verify-camera", [
            'status' => 'denied',
            'reason' => 'User blocked webcam permission.',
        ]);

        $response->assertStatus(403)
            ->assertJson([
                'status' => 'error',
                'verified' => false,
                'error' => 'permission_denied',
            ]);

        $this->assertDatabaseHas('telemetry_logs', [
            'lab_session_id' => $this->session1->id,
            'event_type' => 'webcam_permission_denied',
        ]);
    }

    public function test_prelab_camera_verification_fails_when_no_face_detected()
    {
        $dummyBase64 = 'data:image/jpeg;base64,' . base64_encode('fake-image-bytes');

        $response = $this->postJson("/api/v1/sessions/{$this->session1->id}/verify-camera", [
            'status' => 'granted',
            'face_count' => 0,
            'image_base64' => $dummyBase64,
        ]);

        $response->assertStatus(422)
            ->assertJson([
                'status' => 'error',
                'verified' => false,
                'error' => 'no_face_detected',
            ]);

        $this->assertDatabaseHas('anomalies', [
            'lab_session_id' => $this->session1->id,
            'type' => 'no_face',
            'severity' => 'high',
        ]);
    }

    public function test_prelab_camera_verification_fails_when_multiple_faces_detected()
    {
        $dummyBase64 = 'data:image/jpeg;base64,' . base64_encode('fake-multi-face-image');

        $response = $this->postJson("/api/v1/sessions/{$this->session1->id}/verify-camera", [
            'status' => 'granted',
            'face_count' => 2,
            'image_base64' => $dummyBase64,
        ]);

        $response->assertStatus(422)
            ->assertJson([
                'status' => 'error',
                'verified' => false,
                'error' => 'multiple_faces',
            ]);

        $this->assertDatabaseHas('anomalies', [
            'lab_session_id' => $this->session1->id,
            'type' => 'multiple_faces',
            'severity' => 'medium',
        ]);
    }

    public function test_prelab_camera_verification_succeeds_with_single_face_and_stores_reference_image()
    {
        $dummyBase64 = 'data:image/jpeg;base64,' . base64_encode('valid-face-snapshot-data');

        $response = $this->postJson("/api/v1/sessions/{$this->session1->id}/verify-camera", [
            'status' => 'granted',
            'face_count' => 1,
            'image_base64' => $dummyBase64,
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'status' => 'success',
                'verified' => true,
            ]);

        $refImage = $response->json('reference_image');
        $this->assertNotNull($refImage);
        $this->assertStringContainsString('storage/anomalies/', $refImage);

        // Verify disk file actually written
        $storageRelative = str_replace('storage/', '', $refImage);
        $this->assertFileExists(storage_path('app/public/' . $storageRelative));

        $this->assertDatabaseHas('telemetry_logs', [
            'lab_session_id' => $this->session1->id,
            'event_type' => 'webcam_prelab_verification',
        ]);
    }

    public function test_camera_absence_telemetry_creates_high_severity_anomaly_with_snapshot_file()
    {
        $dummyBase64 = 'data:image/jpeg;base64,' . base64_encode('absence-snapshot-proof');

        $response = $this->postJson("/api/v1/sessions/{$this->session1->id}/telemetry", [
            'event_type' => 'camera_absence',
            'payload' => [
                'face_count' => 0,
                'image_base64' => $dummyBase64,
                'timestamp' => now()->toISOString(),
            ],
        ]);

        $response->assertStatus(200);

        // Student work must NOT be interrupted: session remains in_progress
        $this->session1->refresh();
        $this->assertEquals('in_progress', $this->session1->status);

        $anomaly = Anomaly::where('lab_session_id', $this->session1->id)
            ->where('type', 'no_face')
            ->latest()
            ->first();

        $this->assertNotNull($anomaly);
        $this->assertEquals('high', $anomaly->severity);
        $this->assertNotNull($anomaly->image_path);
        $this->assertStringContainsString('storage/anomalies/', $anomaly->image_path);

        $storageRelative = str_replace('storage/', '', $anomaly->image_path);
        $this->assertFileExists(storage_path('app/public/' . $storageRelative));
    }

    public function test_continuous_webcam_check_with_multiple_faces_creates_anomaly_and_preserves_student_session()
    {
        $dummyBase64 = 'data:image/jpeg;base64,' . base64_encode('two-faces-captured');

        $response = $this->postJson("/api/v1/sessions/{$this->session1->id}/telemetry", [
            'event_type' => 'webcam_check',
            'payload' => [
                'face_count' => 3,
                'image_base64' => $dummyBase64,
            ],
        ]);

        $response->assertStatus(200);

        // Session not blocked
        $this->session1->refresh();
        $this->assertEquals('in_progress', $this->session1->status);

        $this->assertDatabaseHas('anomalies', [
            'lab_session_id' => $this->session1->id,
            'type' => 'multiple_faces',
            'severity' => 'medium',
        ]);
    }

    public function test_instructor_monitoring_streams_camera_absence_anomaly_with_image_path()
    {
        $dummyBase64 = 'data:image/jpeg;base64,' . base64_encode('instructor-alert-image');

        $this->postJson("/api/v1/sessions/{$this->session1->id}/telemetry", [
            'event_type' => 'camera_absence',
            'payload' => [
                'face_count' => 0,
                'image_base64' => $dummyBase64,
            ],
        ]);

        $this->actingAs($this->instructor);
        $response = $this->getJson("/laboratories/{$this->laboratory->id}/monitoring/data");
        $response->assertStatus(200);

        $sessionData = collect($response->json('sessions'))->firstWhere('id', $this->session1->id);
        $this->assertNotNull($sessionData);

        $anomalies = collect($sessionData['anomalies']);
        $noFaceAnomaly = $anomalies->firstWhere('type', 'no_face');

        $this->assertNotNull($noFaceAnomaly);
        $this->assertNotNull($noFaceAnomaly['image_path']);
        $this->assertStringContainsString('storage/anomalies/', $noFaceAnomaly['image_path']);
    }

    public function test_prelab_verification_failed_after_three_attempts_records_high_severity_anomaly_with_snapshot()
    {
        $dummyBase64 = 'data:image/jpeg;base64,' . base64_encode('prelab-failure-proof');

        $response = $this->postJson("/api/v1/sessions/{$this->session1->id}/telemetry", [
            'event_type' => 'prelab_verification_failed',
            'payload' => [
                'attempts' => 3,
                'face_count' => 0,
                'image_base64' => $dummyBase64,
                'timestamp' => now()->toISOString(),
            ],
        ]);

        $response->assertStatus(200);

        $anomaly = Anomaly::where('lab_session_id', $this->session1->id)
            ->where('type', 'no_face')
            ->latest()
            ->first();

        $this->assertNotNull($anomaly);
        $this->assertEquals('high', $anomaly->severity);
        $this->assertStringContainsString('Pre-lab camera presence verification failed after 3 attempts', $anomaly->description);
        $this->assertNotNull($anomaly->image_path);

        $storageRelative = str_replace('storage/', '', $anomaly->image_path);
        $this->assertFileExists(storage_path('app/public/' . $storageRelative));
    }

    public function test_get_session_reflects_camera_verified_state()
    {
        // Initially not camera verified
        $resBefore = $this->getJson("/api/v1/sessions/{$this->session1->id}");
        $resBefore->assertStatus(200);
        $this->assertFalse($resBefore->json('camera_verified'));

        // Log prelab verification success
        TelemetryLog::create([
            'lab_session_id' => $this->session1->id,
            'event_type' => 'webcam_prelab_verification',
            'payload' => ['verified' => true, 'face_count' => 1],
        ]);

        // After verification
        $resAfter = $this->getJson("/api/v1/sessions/{$this->session1->id}");
        $resAfter->assertStatus(200);
        $this->assertTrue($resAfter->json('camera_verified'));
    }

    public function test_v1_and_api_v1_verify_camera_routes_are_both_reachable()
    {
        // Both /v1/labs/{id}/verify-camera and /api/v1/labs/{id}/verify-camera should work
        $resV1 = $this->actingAs($this->student1)->postJson("/v1/labs/{$this->laboratory->id}/verify-camera", [
            'status' => 'granted',
            'face_count' => 1,
            'image_base64' => 'data:image/jpeg;base64,ZmFrZWltYWdl',
        ]);
        $resV1->assertStatus(200);
        $resV1->assertJsonPath('verified', true);

        $resApiV1 = $this->actingAs($this->student1)->postJson("/api/v1/labs/{$this->laboratory->id}/verify-camera", [
            'status' => 'granted',
            'face_count' => 1,
            'image_base64' => 'data:image/jpeg;base64,ZmFrZWltYWdl',
        ]);
        $resApiV1->assertStatus(200);
        $resApiV1->assertJsonPath('verified', true);
    }

    public function test_student_cannot_start_web_lab_session_without_camera_verification()
    {
        $response = $this->actingAs($this->student1)
            ->post("/laboratories/{$this->laboratory->id}/start");

        $response->assertRedirect();
        $response->assertSessionHas('error', 'Webcam presence verification is mandatory before launching the workspace. Please complete the camera check.');
    }

    public function test_student_can_start_web_lab_session_with_camera_verification()
    {
        $response = $this->actingAs($this->student1)
            ->post("/laboratories/{$this->laboratory->id}/start", [
                'camera_verified' => 1,
            ]);

        $response->assertRedirect();
        $this->assertStringStartsWith('vscode://', $response->headers->get('Location'));
    }

    /**
     * Feature 11: Idle Detection in Open Lab
     */
    public function test_idle_timeout_telemetry_in_open_lab_creates_anomaly_without_countdown()
    {
        $this->laboratory->update(['availability_mode' => 'open']);

        $response = $this->postJson("/api/v1/sessions/{$this->session1->id}/telemetry", [
            'event_type' => 'idle_timeout',
            'payload' => [
                'idle_minutes' => 10,
                'timestamp' => now()->toISOString(),
            ],
        ]);

        $response->assertStatus(200);

        $this->assertDatabaseHas('anomalies', [
            'lab_session_id' => $this->session1->id,
            'type' => 'idle_timeout',
            'severity' => 'medium',
            'description' => "Student {$this->student1->name} has been idle for 10 min",
        ]);

        $anomaly = \App\Models\Anomaly::where('lab_session_id', $this->session1->id)
            ->where('type', 'idle_timeout')
            ->first();

        $this->assertNotNull($anomaly);
        $this->assertEquals(10, $anomaly->metadata['idle_minutes']);
        $this->assertFalse($anomaly->metadata['is_live']);
        $this->assertNull($anomaly->metadata['shared_remaining_minutes']);
    }

    /**
     * Feature 11: Idle Detection in Live Lab includes shared countdown context
     */
    public function test_idle_timeout_telemetry_in_live_lab_includes_shared_countdown_context()
    {
        $this->laboratory->update([
            'availability_mode' => 'live',
            'live_status' => 'active',
            'live_duration_minutes' => 45,
            'live_started_at' => now()->subMinutes(15),
            'live_elapsed_seconds' => 0,
        ]);

        $response = $this->postJson("/api/v1/sessions/{$this->session1->id}/telemetry", [
            'event_type' => 'idle_timeout',
            'payload' => [
                'idle_minutes' => 10,
                'timestamp' => now()->toISOString(),
            ],
        ]);

        $response->assertStatus(200);

        $anomaly = \App\Models\Anomaly::where('lab_session_id', $this->session1->id)
            ->where('type', 'idle_timeout')
            ->first();

        $this->assertNotNull($anomaly);
        $this->assertEquals('idle_timeout', $anomaly->type);
        $this->assertEquals('medium', $anomaly->severity);
        $this->assertTrue($anomaly->metadata['is_live']);
        $this->assertEquals(30, $anomaly->metadata['shared_remaining_minutes']);
        $this->assertStringContainsString("Student {$this->student1->name} has been idle for 10 min; 30 min remain in this Live Lab", $anomaly->description);
    }

    /**
     * Feature 11: Recurring idle alert every additional 10 minutes escalates severity
     */
    public function test_recurring_idle_timeout_escalates_severity_every_additional_ten_minutes()
    {
        $this->laboratory->update(['availability_mode' => 'open']);

        // 20 minutes idle
        $res20 = $this->postJson("/api/v1/sessions/{$this->session1->id}/telemetry", [
            'event_type' => 'idle_timeout',
            'payload' => ['idle_minutes' => 20],
        ]);
        $res20->assertStatus(200);

        $this->assertDatabaseHas('anomalies', [
            'lab_session_id' => $this->session1->id,
            'type' => 'idle_timeout',
            'severity' => 'high',
            'description' => "Student {$this->student1->name} has been idle for 20 min",
        ]);

        // 30 minutes idle
        $res30 = $this->postJson("/api/v1/sessions/{$this->session1->id}/telemetry", [
            'event_type' => 'idle_timeout',
            'payload' => ['idle_minutes' => 30],
        ]);
        $res30->assertStatus(200);

        $this->assertDatabaseHas('anomalies', [
            'lab_session_id' => $this->session1->id,
            'type' => 'idle_timeout',
            'severity' => 'high',
            'description' => "Student {$this->student1->name} has been idle for 30 min",
        ]);
    }

    /**
     * Feature 11: Team labs idle detection is per-student and not suppressed by active teammates
     */
    public function test_team_lab_idle_detection_is_per_student_and_not_suppressed_by_teammate()
    {
        $this->laboratory->update(['is_group_lab' => true, 'availability_mode' => 'open']);

        // Create a team group with student1 and student2
        $group = \App\Models\Group::create([
            'lab_id' => $this->laboratory->id,
            'name' => 'Team Alpha',
        ]);
        $group->members()->attach([
            $this->student1->id,
            $this->student2->id,
        ]);

        $this->session1->update(['group_id' => $group->id]);
        $session2 = \App\Models\LabSession::create([
            'lab_id' => $this->laboratory->id,
            'user_id' => $this->student2->id,
            'group_id' => $group->id,
            'status' => 'in_progress',
            'started_at' => now(),
        ]);

        // Teammate 1 is actively working
        $this->postJson("/api/v1/sessions/{$this->session1->id}/telemetry", [
            'event_type' => 'wpm_update',
            'payload' => ['wpm' => 75, 'keystroke_count' => 500],
        ])->assertStatus(200);

        // Teammate 2 goes idle and sends idle_timeout telemetry
        $resIdle = $this->postJson("/api/v1/sessions/{$session2->id}/telemetry", [
            'event_type' => 'idle_timeout',
            'payload' => ['idle_minutes' => 10],
        ]);
        $resIdle->assertStatus(200);

        // Student 2 is flagged with idle anomaly individually
        $this->assertDatabaseHas('anomalies', [
            'lab_session_id' => $session2->id,
            'type' => 'idle_timeout',
            'description' => "Student {$this->student2->name} has been idle for 10 min",
        ]);

        // Student 1 has NO idle anomaly
        $this->assertDatabaseMissing('anomalies', [
            'lab_session_id' => $this->session1->id,
            'type' => 'idle_timeout',
        ]);
    }

    /**
     * Feature 11: WPM interaction keeps idle time in the overall session average divisor
     */
    public function test_wpm_calculation_does_not_exclude_idle_time()
    {
        $session = \App\Models\LabSession::create([
            'lab_id' => $this->laboratory->id,
            'user_id' => $this->student1->id,
            'status' => 'in_progress',
            'started_at' => now()->subMinutes(20),
            'keystroke_count' => 500, // 500 chars / 5 = 100 words
        ]);

        // Over 20 elapsed minutes with idle time included: 100 words / 20 min = 5 WPM
        $overallWpm = $session->calculateOverallWpm();
        $this->assertEquals(5, $overallWpm);

        // Updating wpm via telemetry without explicit wpm payload calculates whole-session average
        $this->postJson("/api/v1/sessions/{$session->id}/telemetry", [
            'event_type' => 'wpm_update',
            'payload' => ['keystroke_count' => 500],
        ])->assertStatus(200);

        $session->refresh();
        $this->assertEquals(5, $session->wpm);
    }
}


