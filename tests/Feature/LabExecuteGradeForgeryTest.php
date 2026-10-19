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
 * Regression tests for grade forging through /execute: a program can print any string, so
 * running code must never award tasks or set the score, and it only works mid-attempt.
 */
class LabExecuteGradeForgeryTest extends TestCase
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

        $this->laboratory = Laboratory::create([
            'module_id' => $module->id,
            'title' => 'Loops',
            'description' => 'Write a loop.',
            'time_limit' => 45,
            'is_group_lab' => false,
            'tasks_definition' => [
                ['id' => 1, 'task' => 'Print a greeting', 'command' => 'hello'],
                ['id' => 2, 'task' => 'Use a for loop', 'command' => 'regex:/for\s*\(/'],
            ],
        ]);
    }

    private function labSession(string $status = 'in_progress'): LabSession
    {
        return LabSession::create([
            'lab_id' => $this->laboratory->id,
            'user_id' => $this->student->id,
            'status' => $status,
            'started_at' => now(),
            'performance_score' => 0.0,
        ]);
    }

    public function test_code_that_prints_every_task_check_earns_no_tasks_and_no_score(): void
    {
        $session = $this->labSession();

        // Prints the first check string and contains the second check's pattern in a comment
        $this->actingAs($this->student)
            ->postJson("/api/v1/sessions/{$session->id}/execute", [
                'code' => '<?php /* for ( */ echo "hello";',
                'language' => 'php',
            ])
            ->assertOk()
            ->assertJson(['completed_tasks' => [], 'performance_score' => 0]);

        $session->refresh();
        $this->assertEmpty($session->completed_tasks ?? []);
        $this->assertEquals(0.0, $session->performance_score);
        $this->assertDatabaseMissing('telemetry_logs', ['lab_session_id' => $session->id, 'event_type' => 'task_verified']);
    }

    public function test_execute_then_end_cannot_finalize_a_forged_score(): void
    {
        $session = $this->labSession();

        $this->actingAs($this->student)->postJson("/api/v1/sessions/{$session->id}/execute", [
            'code' => '<?php /* for ( */ echo "hello";',
            'language' => 'php',
        ])->assertOk();

        $this->actingAs($this->student)->postJson("/api/v1/sessions/{$session->id}/end")->assertOk();

        // Leaving doesn't finalize the attempt, and the score stays untouched
        $session->refresh();
        $this->assertSame('in_progress', $session->status);
        $this->assertEquals(0.0, $session->performance_score);
    }

    public function test_code_cannot_be_run_once_the_session_has_ended(): void
    {
        foreach (['completed', 'abandoned'] as $status) {
            $session = $this->labSession($status);

            $this->actingAs($this->student)
                ->postJson("/api/v1/sessions/{$session->id}/execute", ['code' => '<?php echo "hello";', 'language' => 'php'])
                ->assertStatus(409);

            $this->assertSame(0, TelemetryLog::where('lab_session_id', $session->id)->count());
        }
    }
}
