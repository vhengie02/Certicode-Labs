<?php

namespace Tests\Feature;

use App\Events\AnomalyDetected;
use App\Events\ChatMessageSent;
use App\Events\DiffUpdated;
use App\Events\LeaderboardUpdated;
use App\Models\Certificate;
use App\Models\Laboratory;
use App\Models\LabSession;
use App\Models\Module;
use App\Models\SchoolClass;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class EndToEndLabLifecycleTest extends TestCase
{
    use RefreshDatabase;

    protected User $instructor;
    protected User $student;
    protected SchoolClass $class;
    protected Module $module;
    protected Laboratory $laboratory;

    protected function setUp(): void
    {
        parent::setUp();

        // 1. Setup Instructor and Student
        $this->instructor = User::create([
            'name' => 'Prof. Turing',
            'email' => 'turing@example.com',
            'password' => bcrypt('password'),
            'role' => 'instructor',
        ]);

        $this->student = User::create([
            'name' => 'Ada Lovelace',
            'email' => 'ada@example.com',
            'password' => bcrypt('password'),
            'role' => 'student',
        ]);

        // 2. Instructor creates class with 75% threshold
        $this->class = SchoolClass::create([
            'instructor_id' => $this->instructor->id,
            'name' => 'Advanced Algorithms & OOP',
            'code' => 'CS-ADV-101',
            'description' => 'Comprehensive masterclass.',
            'passing_threshold' => 75,
            'status' => 'active',
        ]);

        $this->class->students()->attach($this->student->id, ['status' => 'enrolled']);

        // 3. Create module and Live Lab with multi-file starter manifest
        $this->module = Module::create([
            'class_id' => $this->class->id,
            'title' => 'Module 1: Robust Java OOP',
            'description' => 'Exceptions and Design Patterns',
            'order' => 1,
        ]);

        $this->laboratory = Laboratory::create([
            'class_id' => $this->class->id,
            'module_id' => $this->module->id,
            'title' => 'E2E Capstone Live Challenge',
            'description' => 'Solve custom exception hierarchies under proctored live conditions.',
            'availability_mode' => 'live',
            'live_duration_minutes' => 60,
            'live_status' => 'not_started',
            'time_limit' => 60,
            'is_group_lab' => false,
            'starter_files' => [
                [
                    'name' => 'Solution.java',
                    'content' => 'public class Solution { public static void main(String[] args) {} }',
                    'is_primary' => true,
                    'is_readonly' => false,
                ],
                [
                    'name' => 'InvalidAgeException.java',
                    'content' => 'public class InvalidAgeException extends Exception {}',
                    'is_primary' => false,
                    'is_readonly' => false,
                ],
            ],
            'tasks_definition' => [
                ['id' => 1, 'task' => 'Implement Solution class', 'command' => 'regex:class\s+Solution'],
                ['id' => 2, 'task' => 'Implement InvalidAgeException', 'command' => 'regex:class\s+InvalidAgeException'],
            ],
            'rubric' => 'Evaluate based on OOP structure and exception propagation.',
        ]);
    }

    /**
     * Test full end-to-end user lifecycle across all 9 platform features.
     */
    public function test_complete_end_to_end_lab_and_certification_lifecycle(): void
    {
        Event::fake([
            DiffUpdated::class,
            ChatMessageSent::class,
            LeaderboardUpdated::class,
            AnomalyDetected::class,
        ]);

        // Step 1: Student attempts to start lab before instructor opens it -> 403 live_not_started
        $response = $this->actingAs($this->student)->postJson("/api/v1/labs/{$this->laboratory->id}/start");
        $response->assertStatus(403)
            ->assertJsonPath('error', 'live_not_started');

        // Step 2: Instructor opens the Live Lab
        $openResponse = $this->actingAs($this->instructor)->post("/laboratories/{$this->laboratory->id}/open-live", [
            'duration_minutes' => 60,
        ]);
        $openResponse->assertRedirect();
        $this->laboratory->refresh();
        $this->assertEquals('active', $this->laboratory->live_status);
        $this->assertNotNull($this->laboratory->live_started_at);

        // Step 3: Pre-Lab Camera Proctoring Verification Gate
        // 3a. Rejection if permission denied
        $prelabDenied = $this->actingAs($this->student)->postJson("/api/v1/labs/{$this->laboratory->id}/verify-camera", [
            'status' => 'denied',
        ]);
        $prelabDenied->assertStatus(403)
            ->assertJsonPath('error', 'permission_denied');

        // 3b. Rejection if no face detected in frame
        $prelabNoFace = $this->actingAs($this->student)->postJson("/api/v1/labs/{$this->laboratory->id}/verify-camera", [
            'status' => 'granted',
            'face_count' => 0,
            'image_base64' => 'data:image/jpeg;base64,ZmFrZWltYWdl',
        ]);
        $prelabNoFace->assertStatus(422)
            ->assertJsonPath('error', 'no_face_detected');

        // 3c. Success when 1 face is verified
        $prelabPass = $this->actingAs($this->student)->postJson("/api/v1/labs/{$this->laboratory->id}/verify-camera", [
            'status' => 'granted',
            'face_count' => 1,
            'image_base64' => 'data:image/jpeg;base64,dmFsaWRmYWNlaW1hZ2U=',
        ]);
        $prelabPass->assertStatus(200)
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('verified', true);

        // Step 4: Student successfully connects and starts session
        $startResponse = $this->actingAs($this->student)->postJson("/api/v1/labs/{$this->laboratory->id}/start");
        $startResponse->assertStatus(200);
        $sessionId = $startResponse->json('session.id');
        $session = LabSession::findOrFail($sessionId);
        $this->assertEquals('in_progress', $session->status);

        // Step 5: Student sends line-level diff updates (Feature 1) -> Dispatches DiffUpdated
        $diffResponse = $this->actingAs($this->student)->postJson("/api/v1/sessions/{$sessionId}/diff", [
            'lines_added' => 18,
            'lines_deleted' => 2,
            'lines_modified' => 5,
            'files' => [
                ['name' => 'Solution.java', 'added' => 18, 'deleted' => 2, 'modified' => 5],
            ],
        ]);
        $diffResponse->assertStatus(200);
        Event::assertDispatched(DiffUpdated::class, function ($event) use ($sessionId) {
            return $event->sessionId === $sessionId && $event->diffStats['lines_added'] === 18;
        });

        // Step 6: Team chat exchange (Feature 2) -> Dispatches ChatMessageSent
        $chatResponse = $this->actingAs($this->student)->postJson("/api/v1/sessions/{$sessionId}/chat", [
            'message' => 'Exception hierarchy implemented successfully.',
            'code_snippet' => 'throw new InvalidAgeException();',
        ]);
        $chatResponse->assertStatus(200);
        Event::assertDispatched(ChatMessageSent::class, function ($event) use ($sessionId) {
            return $event->sessionId === $sessionId && str_contains($event->chat['message'], 'hierarchy implemented');
        });

        // Step 7: Progress evaluation and Leaderboard Update (Feature 5) -> Dispatches LeaderboardUpdated
        $validCode = "public class Solution { public static void main(String[] args) {} }\nclass InvalidAgeException extends Exception {}";
        $checkProgress = $this->actingAs($this->student)->postJson("/api/v1/sessions/{$sessionId}/check-progress", [
            'code' => $validCode,
            'language' => 'java',
        ]);
        $checkProgress->assertStatus(200);
        Event::assertDispatched(LeaderboardUpdated::class, function ($event) use ($sessionId) {
            return $event->sessionId === $sessionId;
        });

        // Step 8: Telemetry sensor tracking (Features 4 & 8)
        // 8a. Focus loss tracking
        $this->actingAs($this->student)->postJson("/api/v1/sessions/{$sessionId}/telemetry", [
            'event_type' => 'focus_lost',
            'payload' => ['blurred_at' => now()->toIso8601String()],
        ]);
        $session->refresh();
        $this->assertEquals(1, $session->focus_lost_count);

        // 8b. Camera absence non-interruptive anomaly -> Dispatches AnomalyDetected
        $cameraAbsence = $this->actingAs($this->student)->postJson("/api/v1/sessions/{$sessionId}/telemetry", [
            'event_type' => 'camera_absence',
            'payload' => [
                'image_base64' => 'data:image/jpeg;base64,ZW1wdHlyb29t',
            ],
        ]);
        $cameraAbsence->assertStatus(200);
        Event::assertDispatched(AnomalyDetected::class, function ($event) use ($sessionId) {
            return $event->sessionId === $sessionId && $event->anomaly['type'] === 'no_face';
        });

        // Verify session remains active and not blocked mid-session
        $session->refresh();
        $this->assertEquals('in_progress', $session->status);

        // Step 9: Live Lab Timer expiry and automated session auto-close (Feature 9)
        $this->laboratory->update([
            'live_started_at' => Carbon::now()->subMinutes(65), // Expired 5 minutes ago
        ]);

        // Run auto-closer artisan command
        $this->artisan('certicode:close-expired-live-labs')->assertExitCode(0);

        $this->laboratory->refresh();
        $session->refresh();
        $this->assertEquals('closed', $this->laboratory->live_status);
        $this->assertEquals('completed', $session->status);

        // Step 10: Course completion evaluation & E-Certificate Generation (Feature 7B)
        $session->update([
            'performance_score' => 90.0,
            'completed_tasks' => [1, 2],
        ]);

        // Conclude course class
        $endCourseResponse = $this->actingAs($this->instructor)->post("/classes/{$this->class->id}/end");
        $endCourseResponse->assertRedirect();

        $this->class->refresh();
        $this->assertEquals('completed', $this->class->status);

        // Verify certificate generated with verification code
        $certificate = Certificate::where('user_id', $this->student->id)
            ->where('class_id', $this->class->id)
            ->first();

        $this->assertNotNull($certificate, 'E-Certificate should be automatically generated for student >= 75%');
        $this->assertStringStartsWith('CERT-', $certificate->verification_code);

        // Step 11: Public Certificate Verification Portal validation
        $portalResponse = $this->get('/certificates/' . $certificate->id);
        $portalResponse->assertStatus(200)
            ->assertSee($certificate->verification_code)
            ->assertSee($this->student->name)
            ->assertSee($this->class->name);
    }
}
