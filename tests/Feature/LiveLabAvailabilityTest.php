<?php

namespace Tests\Feature;

use App\Models\Laboratory;
use App\Models\LabSession;
use App\Models\SchoolClass;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LiveLabAvailabilityTest extends TestCase
{
    use RefreshDatabase;

    protected User $instructor;
    protected User $student1;
    protected User $student2;
    protected SchoolClass $class;

    protected function setUp(): void
    {
        parent::setUp();

        $this->instructor = User::create([
            'name' => 'Professor Smith',
            'email' => 'prof.smith@example.com',
            'password' => bcrypt('password'),
            'role' => 'instructor',
        ]);

        $this->student1 = User::create([
            'name' => 'Alice Student',
            'email' => 'alice@example.com',
            'password' => bcrypt('password'),
            'role' => 'student',
        ]);

        $this->student2 = User::create([
            'name' => 'Bob Student',
            'email' => 'bob@example.com',
            'password' => bcrypt('password'),
            'role' => 'student',
        ]);

        $this->class = SchoolClass::create([
            'name' => 'Computer Systems',
            'code' => 'CS-301',
            'instructor_id' => $this->instructor->id,
            'description' => 'Operating Systems & Concurrency',
        ]);
    }

    /**
     * Test creating Open Lab stores default availability_mode = 'open'.
     */
    public function test_open_lab_is_created_with_open_availability_mode(): void
    {
        $lab = Laboratory::create([
            'class_id' => $this->class->id,
            'title' => 'Open Lab - Shell Basics',
            'description' => 'Work at your own pace',
            'time_limit' => 60,
            'availability_mode' => 'open',
            'tasks_definition' => [
                ['id' => 1, 'task' => 'Check pwd', 'command' => 'pwd'],
            ],
        ]);

        $this->assertTrue($lab->isOpenLab());
        $this->assertFalse($lab->isLiveLab());
        $this->assertEquals('open', $lab->availability_mode);

        // Student can start session directly
        $response = $this->actingAs($this->student1)
            ->postJson("/api/v1/labs/{$lab->id}/start");

        $response->assertStatus(200);
        $this->assertDatabaseHas('lab_sessions', [
            'lab_id' => $lab->id,
            'user_id' => $this->student1->id,
            'status' => 'in_progress',
        ]);
    }

    /**
     * Test creating Live Lab stores availability_mode = 'live' and live_duration_minutes.
     */
    public function test_live_lab_is_created_with_live_availability_mode_and_duration(): void
    {
        $lab = Laboratory::create([
            'class_id' => $this->class->id,
            'title' => 'Live Lab - Final Exam',
            'description' => 'Synchronous 60-minute practical exam',
            'time_limit' => 60,
            'availability_mode' => 'live',
            'live_duration_minutes' => 60,
            'live_status' => 'not_started',
            'tasks_definition' => [
                ['id' => 1, 'task' => 'Write buffer', 'command' => 'gcc buffer.c -o buffer'],
            ],
        ]);

        $this->assertTrue($lab->isLiveLab());
        $this->assertFalse($lab->isOpenLab());
        $this->assertTrue($lab->isLiveNotStarted());
        $this->assertEquals(60, $lab->live_duration_minutes);
        $this->assertEquals(3600, $lab->getLiveTotalDurationSeconds());
    }

    /**
     * Test student is blocked from starting a Live Lab before instructor opens it.
     */
    public function test_student_is_blocked_from_starting_live_lab_before_open(): void
    {
        $lab = Laboratory::create([
            'class_id' => $this->class->id,
            'title' => 'Live Exam',
            'description' => 'Instructor must open first',
            'availability_mode' => 'live',
            'live_duration_minutes' => 45,
            'live_status' => 'not_started',
        ]);

        // API attempt
        $apiResponse = $this->actingAs($this->student1)
            ->postJson("/api/v1/labs/{$lab->id}/start");

        $apiResponse->assertStatus(403);
        $apiResponse->assertJson([
            'error' => 'live_not_started',
        ]);

        // Web attempt
        $webResponse = $this->actingAs($this->student1)
            ->post("/laboratories/{$lab->id}/start");

        $webResponse->assertRedirect();
        $webResponse->assertSessionHas('error');

        $this->assertDatabaseMissing('lab_sessions', [
            'lab_id' => $lab->id,
            'user_id' => $this->student1->id,
        ]);
    }

    /**
     * Test instructor can manually open Live Lab and start the shared countdown.
     */
    public function test_instructor_can_open_live_lab(): void
    {
        $lab = Laboratory::create([
            'class_id' => $this->class->id,
            'title' => 'Live Lab - Midterm',
            'description' => 'Midterm examination lab',
            'availability_mode' => 'live',
            'live_duration_minutes' => 30,
            'live_status' => 'not_started',
        ]);

        $response = $this->actingAs($this->instructor)
            ->postJson("/api/v1/labs/{$lab->id}/open-live", [
                'duration_minutes' => 30,
            ]);

        $response->assertStatus(200);
        $response->assertJson([
            'status' => 'success',
            'laboratory' => [
                'live_status' => 'active',
                'live_duration_minutes' => 30,
            ],
        ]);

        $lab->refresh();
        $this->assertTrue($lab->isLiveActive());
        $this->assertNotNull($lab->live_started_at);
        $this->assertEquals(0, $lab->live_elapsed_seconds);
    }

    /**
     * Test non-instructor cannot open a Live Lab.
     */
    public function test_student_cannot_open_live_lab(): void
    {
        $lab = Laboratory::create([
            'class_id' => $this->class->id,
            'title' => 'Live Lab',
            'description' => 'Student should not open this',
            'availability_mode' => 'live',
            'live_duration_minutes' => 30,
            'live_status' => 'not_started',
        ]);

        $response = $this->actingAs($this->student1)
            ->postJson("/api/v1/labs/{$lab->id}/open-live");

        $response->assertStatus(403);
    }

    /**
     * Test shared countdown: late joiner gets only the remaining window, not a fresh duration.
     */
    public function test_late_joiner_gets_shared_countdown_remaining_time(): void
    {
        // 60-minute live lab
        $lab = Laboratory::create([
            'class_id' => $this->class->id,
            'title' => 'Live Synchronous Challenge',
            'description' => 'A synchronous live lab challenge',
            'availability_mode' => 'live',
            'live_duration_minutes' => 60,
            'live_status' => 'active',
            // Opened 15 minutes ago
            'live_started_at' => Carbon::now()->subMinutes(15),
            'live_elapsed_seconds' => 0,
            'tasks_definition' => [
                ['id' => 1, 'task' => 'Task 1', 'command' => 'test'],
            ],
        ]);

        // Student 1 starts now (15 minutes in)
        $response1 = $this->actingAs($this->student1)
            ->postJson("/api/v1/labs/{$lab->id}/start");

        $response1->assertStatus(200);
        $sessionId1 = $response1->json('session.id');

        // Check session state via API
        $sessionResponse = $this->actingAs($this->student1)
            ->getJson("/api/v1/sessions/{$sessionId1}");

        $sessionResponse->assertStatus(200);
        $sessionData = $sessionResponse->json();

        $this->assertEquals('live', $sessionData['availability_mode']);
        // Time remaining should be approximately 45 minutes (2700 seconds +/- 5 seconds)
        $this->assertLessThanOrEqual(2705, $sessionData['time_remaining_seconds']);
        $this->assertGreaterThanOrEqual(2690, $sessionData['time_remaining_seconds']);
    }

    /**
     * Test auto-close and auto-submit when Live Lab shared duration expires.
     */
    public function test_live_lab_auto_closes_and_submits_active_sessions_on_expiry(): void
    {
        // 30-minute live lab that started 35 minutes ago
        $lab = Laboratory::create([
            'class_id' => $this->class->id,
            'title' => 'Expired Live Lab',
            'description' => 'Test expiry auto-close',
            'availability_mode' => 'live',
            'live_duration_minutes' => 30,
            'live_status' => 'active',
            'live_started_at' => Carbon::now()->subMinutes(35),
            'live_elapsed_seconds' => 0,
        ]);

        // In-progress session
        $session = LabSession::create([
            'lab_id' => $lab->id,
            'user_id' => $this->student1->id,
            'status' => 'in_progress',
            'started_at' => Carbon::now()->subMinutes(30),
        ]);

        // Run auto-close check
        $closed = $lab->checkAndAutoCloseLive();
        $this->assertTrue($closed);

        $lab->refresh();
        $session->refresh();

        $this->assertTrue($lab->isLiveClosed());
        $this->assertEquals(0, $lab->getRemainingLiveSeconds());
        $this->assertEquals('completed', $session->status);
        $this->assertNotNull($session->ended_at);
        $this->assertNotNull($session->closed_at);
    }

    /**
     * Test late joiner is blocked when Live Lab has expired.
     */
    public function test_late_joiner_is_blocked_when_live_lab_has_expired(): void
    {
        // 10-minute lab started 15 minutes ago
        $lab = Laboratory::create([
            'class_id' => $this->class->id,
            'title' => 'Expired Live Lab',
            'description' => 'Test late joiner block',
            'availability_mode' => 'live',
            'live_duration_minutes' => 10,
            'live_status' => 'active',
            'live_started_at' => Carbon::now()->subMinutes(15),
            'live_elapsed_seconds' => 0,
        ]);

        // Late student tries to start session
        $response = $this->actingAs($this->student2)
            ->postJson("/api/v1/labs/{$lab->id}/start");

        $response->assertStatus(403);
        $response->assertJson([
            'error' => 'live_expired',
        ]);

        // Web attempt also blocked
        $webResponse = $this->actingAs($this->student2)
            ->post("/laboratories/{$lab->id}/start");

        $webResponse->assertRedirect();
        $webResponse->assertSessionHas('error');
    }

    /**
     * Test instructor manually ending a Live Lab early.
     */
    public function test_instructor_can_end_live_lab_early(): void
    {
        $lab = Laboratory::create([
            'class_id' => $this->class->id,
            'title' => 'Early End Live Lab',
            'description' => 'Test ending early',
            'availability_mode' => 'live',
            'live_duration_minutes' => 60,
            'live_status' => 'active',
            'live_started_at' => Carbon::now()->subMinutes(10),
            'live_elapsed_seconds' => 0,
        ]);

        $session = LabSession::create([
            'lab_id' => $lab->id,
            'user_id' => $this->student1->id,
            'status' => 'in_progress',
            'started_at' => Carbon::now()->subMinutes(10),
        ]);

        $response = $this->actingAs($this->instructor)
            ->postJson("/api/v1/labs/{$lab->id}/end-live");

        $response->assertStatus(200);
        $response->assertJson([
            'status' => 'success',
            'laboratory' => [
                'live_status' => 'closed',
            ],
        ]);

        $lab->refresh();
        $session->refresh();

        $this->assertTrue($lab->isLiveClosed());
        $this->assertGreaterThanOrEqual(590, $lab->live_elapsed_seconds);
        $this->assertEquals('completed', $session->status);
    }

    /**
     * Test reopening Live Lab carries forward only remaining leftover time.
     */
    public function test_reopening_live_lab_carries_forward_only_leftover_time(): void
    {
        // 60-minute lab paused after 20 minutes (1200 seconds)
        $lab = Laboratory::create([
            'class_id' => $this->class->id,
            'title' => 'Reopenable Live Lab',
            'description' => 'Test reopening with leftover',
            'availability_mode' => 'live',
            'live_duration_minutes' => 60,
            'live_status' => 'closed',
            'live_started_at' => null,
            'live_elapsed_seconds' => 1200, // 20 minutes elapsed
        ]);

        $this->assertEquals(2400, $lab->getRemainingLiveSeconds()); // 40 minutes remaining

        // Reopen without extra minutes
        $response = $this->actingAs($this->instructor)
            ->postJson("/api/v1/labs/{$lab->id}/reopen-live");

        $response->assertStatus(200);
        $response->assertJson([
            'status' => 'success',
            'laboratory' => [
                'live_status' => 'active',
            ],
        ]);

        $lab->refresh();
        $this->assertTrue($lab->isLiveActive());
        // Remaining time is still ~2400 seconds (40 minutes), NOT reset to 60 minutes
        $this->assertEquals(2400, $lab->getRemainingLiveSeconds(), '', 5);
    }

    /**
     * Test reopening an already expired Live Lab requires add_minutes.
     */
    public function test_reopening_expired_live_lab_requires_additional_minutes(): void
    {
        // 30-minute lab that used all 1800 seconds
        $lab = Laboratory::create([
            'class_id' => $this->class->id,
            'title' => 'Fully Expired Lab',
            'description' => 'Test expiring requires extension',
            'availability_mode' => 'live',
            'live_duration_minutes' => 30,
            'live_status' => 'closed',
            'live_started_at' => null,
            'live_elapsed_seconds' => 1800,
        ]);

        // Attempt reopening without adding minutes
        $response = $this->actingAs($this->instructor)
            ->postJson("/api/v1/labs/{$lab->id}/reopen-live");

        $response->assertStatus(422);

        // Reopen with +15 minutes
        $responseWithAdd = $this->actingAs($this->instructor)
            ->postJson("/api/v1/labs/{$lab->id}/reopen-live", [
                'add_minutes' => 15,
            ]);

        $responseWithAdd->assertStatus(200);
        $lab->refresh();

        $this->assertTrue($lab->isLiveActive());
        $this->assertEquals(45, $lab->live_duration_minutes);
        $this->assertEquals(900, $lab->getRemainingLiveSeconds(), '', 5);
    }

    /**
     * Test artisan scheduled command certicode:close-expired-live-labs.
     */
    public function test_artisan_command_closes_expired_live_labs(): void
    {
        // Expired live lab
        $lab = Laboratory::create([
            'class_id' => $this->class->id,
            'title' => 'Artisan Close Lab',
            'description' => 'Test artisan command auto-close',
            'availability_mode' => 'live',
            'live_duration_minutes' => 10,
            'live_status' => 'active',
            'live_started_at' => Carbon::now()->subMinutes(20),
            'live_elapsed_seconds' => 0,
        ]);

        $session = LabSession::create([
            'lab_id' => $lab->id,
            'user_id' => $this->student1->id,
            'status' => 'in_progress',
            'started_at' => Carbon::now()->subMinutes(15),
        ]);

        $this->artisan('certicode:close-expired-live-labs')
            ->expectsOutputToContain('1 expired live lab(s) closed')
            ->assertExitCode(0);

        $lab->refresh();
        $session->refresh();

        $this->assertTrue($lab->isLiveClosed());
        $this->assertEquals('completed', $session->status);
    }

    /**
     * Test /api/cron/tick endpoint executes scheduled commands and returns status.
     */
    public function test_cron_tick_endpoint_triggers_closers(): void
    {
        $response = $this->getJson('/api/cron/tick');
        $response->assertStatus(200)
            ->assertJsonPath('status', 'success')
            ->assertJsonStructure([
                'status',
                'timestamp',
                'results' => ['live_labs', 'classes'],
            ]);
    }
}

