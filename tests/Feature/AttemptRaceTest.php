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
 * Two starts at the same moment must not give a student two open attempts on a one-attempt
 * lab. The race is simulated by opening the competing attempt just before the start's own
 * insert, after its attempt check has passed.
 */
class AttemptRaceTest extends TestCase
{
    use RefreshDatabase;

    private User $instructor;
    private User $student;
    private Laboratory $lab;

    protected function setUp(): void
    {
        parent::setUp();

        $this->instructor = User::create(['name' => 'Prof', 'email' => 'prof@example.com', 'password' => bcrypt('password'), 'role' => 'instructor']);
        $this->student = User::create(['name' => 'Ana', 'email' => 'ana@example.com', 'password' => bcrypt('password'), 'role' => 'student']);
        $class = SchoolClass::create(['name' => 'CS 101', 'code' => 'CS101', 'instructor_id' => $this->instructor->id]);
        $class->students()->attach([$this->student->id => ['status' => 'enrolled']]);
        $module = Module::create(['class_id' => $class->id, 'title' => 'Module 1', 'content' => 'Content', 'order_index' => 0]);
        $this->lab = Laboratory::create(['module_id' => $module->id, 'title' => 'Lab', 'description' => 'D', 'time_limit' => 0, 'max_attempts' => 1, 'tasks_definition' => []]);
    }

    /**
     * The other request wins: right after our start has counted the student's attempts (and
     * found none), the other one commits its attempt.
     */
    private function loseTheRace(): void
    {
        $inserted = false;
        DB::listen(function ($query) use (&$inserted) {
            if ($inserted || !str_contains($query->sql, 'count(*)') || !str_contains($query->sql, 'lab_sessions')) {
                return;
            }
            $inserted = true;
            DB::table('lab_sessions')->insert([
                'lab_id' => $this->lab->id,
                'user_id' => $this->student->id,
                'status' => 'in_progress',
                'started_at' => now(),
                'performance_score' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        });
    }

    private function openAttempts(): int
    {
        return LabSession::where('lab_id', $this->lab->id)->where('user_id', $this->student->id)->where('status', 'in_progress')->count();
    }

    public function test_concurrent_api_starts_share_one_attempt(): void
    {
        $this->loseTheRace();

        $response = $this->actingAs($this->student)->postJson("/api/v1/labs/{$this->lab->id}/start");

        $response->assertOk();
        $this->assertSame(1, $this->openAttempts());
        $this->assertSame(LabSession::where('lab_id', $this->lab->id)->value('id'), $response->json('session.id'));
    }

    public function test_concurrent_web_starts_share_one_attempt(): void
    {
        $this->loseTheRace();

        $this->actingAs($this->student)
            ->withSession(["camera_verified_lab_{$this->lab->id}" => ['verified_at' => now()->toIso8601String()]])
            ->postJson("/laboratories/{$this->lab->id}/start")
            ->assertSuccessful();

        $this->assertSame(1, $this->openAttempts());
    }

    public function test_reopening_is_refused_while_another_attempt_is_open(): void
    {
        $this->lab->update(['max_attempts' => 0]);
        $old = LabSession::create(['lab_id' => $this->lab->id, 'user_id' => $this->student->id, 'status' => 'completed', 'started_at' => now(), 'ended_at' => now()]);
        LabSession::create(['lab_id' => $this->lab->id, 'user_id' => $this->student->id, 'status' => 'in_progress', 'started_at' => now()]);

        $this->actingAs($this->instructor)->postJson("/api/v1/sessions/{$old->id}/reopen")
            ->assertStatus(409)
            ->assertJsonPath('error', 'other_attempt_open');

        $this->assertSame('completed', $old->fresh()->status);
    }

    public function test_migration_keeps_the_newest_of_duplicate_open_attempts(): void
    {
        DB::statement('DROP INDEX IF EXISTS lab_sessions_one_open_attempt');
        $older = LabSession::create(['lab_id' => $this->lab->id, 'user_id' => $this->student->id, 'status' => 'in_progress', 'started_at' => now()]);
        $newer = LabSession::create(['lab_id' => $this->lab->id, 'user_id' => $this->student->id, 'status' => 'in_progress', 'started_at' => now()]);

        (require database_path('migrations/2026_10_19_000300_one_open_attempt_per_student.php'))->up();

        $this->assertSame('abandoned', $older->fresh()->status);
        $this->assertSame('in_progress', $newer->fresh()->status);
        $this->expectException(\Illuminate\Database\UniqueConstraintViolationException::class);
        LabSession::create(['lab_id' => $this->lab->id, 'user_id' => $this->student->id, 'status' => 'in_progress', 'started_at' => now()]);
    }
}
