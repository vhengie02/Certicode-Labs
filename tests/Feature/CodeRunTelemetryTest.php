<?php

namespace Tests\Feature;

use App\Models\Laboratory;
use App\Models\LabSession;
use App\Models\Module;
use App\Models\SchoolClass;
use App\Models\TelemetryLog;
use App\Models\User;
use App\Services\LlmEvaluationService;
use App\Services\SandboxExecutionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Regression tests for "status: error" next to a 100% score on the session timeline: it was the
 * code run's status, read as a grading failure, and the reason for it was thrown away.
 */
class CodeRunTelemetryTest extends TestCase
{
    use RefreshDatabase;

    private User $student;

    private User $instructor;

    private LabSession $session;

    private const CODE = ['code' => 'public class Main { public static void main(String[] a) { } }', 'language' => 'java'];

    protected function setUp(): void
    {
        parent::setUp();

        $this->student = User::create(['name' => 'Student', 'email' => 'student@example.com', 'password' => bcrypt('password'), 'role' => 'student']);
        $this->instructor = User::create(['name' => 'Prof', 'email' => 'prof@example.com', 'password' => bcrypt('password'), 'role' => 'instructor']);
        $class = SchoolClass::create(['name' => 'Java 101', 'code' => 'JAVA-101', 'instructor_id' => $this->instructor->id]);
        $class->students()->attach($this->student->id, ['status' => 'enrolled']);
        $module = Module::create(['class_id' => $class->id, 'title' => 'Module', 'content' => 'Content']);
        $lab = Laboratory::create(['module_id' => $module->id, 'title' => 'Print', 'description' => 'Print 1.', 'time_limit' => 45, 'is_group_lab' => false, 'tasks_definition' => [['id' => 1, 'task' => 'Print 1']]]);
        $this->session = LabSession::create(['lab_id' => $lab->id, 'user_id' => $this->student->id, 'status' => 'in_progress', 'started_at' => now(), 'performance_score' => 0]);

        $this->app->instance(LlmEvaluationService::class, new class extends LlmEvaluationService
        {
            public function __construct() {}

            public function evaluate(LabSession $session, string $code, string $language, array $diagnostics = [], array $otherFiles = []): array
            {
                return ['correctness_score' => 100.0, 'tasks' => [['id' => 1, 'completed' => true]]];
            }
        });
    }

    private function submitWithCodeRun(array $result): array
    {
        $this->mock(SandboxExecutionService::class)->shouldReceive('execute')->andReturn($result);

        $this->actingAs($this->student)->postJson("/api/v1/sessions/{$this->session->id}/submit", self::CODE)->assertOk();

        return TelemetryLog::where('lab_session_id', $this->session->id)
            ->where('event_type', 'session_completed')
            ->sole()
            ->payload;
    }

    private function timeline()
    {
        return $this->actingAs($this->instructor)->get(route('sessions.telemetry-timeline', $this->session->id))->assertOk();
    }

    public function test_a_failed_code_run_shows_why_on_the_timeline_without_reading_as_a_grade(): void
    {
        $payload = $this->submitWithCodeRun(['status' => 'error', 'output' => '', 'errors' => 'Java Compilation Error: cannot find symbol', 'execution_time_ms' => 5]);

        $this->assertSame('error', $payload['execution_status']);
        $this->assertSame('Java Compilation Error: cannot find symbol', $payload['execution_error']);

        $this->timeline()
            ->assertSee('Code run:')
            ->assertSee('Java Compilation Error: cannot find symbol')
            ->assertDontSee('tasks passed, status');
    }

    public function test_a_long_error_is_cut_short_in_the_log(): void
    {
        $payload = $this->submitWithCodeRun(['status' => 'error', 'output' => '', 'errors' => str_repeat('x', 2000), 'execution_time_ms' => 5]);

        $this->assertLessThanOrEqual(503, mb_strlen($payload['execution_error']));
    }

    public function test_a_successful_code_run_saves_no_error(): void
    {
        $payload = $this->submitWithCodeRun(['status' => 'success', 'output' => '1', 'errors' => null, 'execution_time_ms' => 5]);

        $this->assertNull($payload['execution_error']);
        $this->timeline()->assertSee('Code run:');
    }

    public function test_a_completion_with_no_code_run_shows_no_run_line(): void
    {
        TelemetryLog::create([
            'lab_session_id' => $this->session->id,
            'event_type' => 'session_completed',
            'payload' => ['final_score' => 100, 'completed_tasks_count' => 1],
        ]);

        $this->timeline()->assertDontSee('Code run:');
    }
}
