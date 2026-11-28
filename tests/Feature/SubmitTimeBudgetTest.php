<?php

namespace Tests\Feature;

use App\Models\Laboratory;
use App\Models\LabSession;
use App\Models\Module;
use App\Models\SchoolClass;
use App\Models\User;
use App\Services\LlmEvaluationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Regression tests for submits running past Vercel's 60 s limit: the Claude grade (45 s plus a
 * retry) used to share the function with a code run, and the code was only saved at the very
 * end, so a cut-off request lost the submission.
 */
class SubmitTimeBudgetTest extends TestCase
{
    use RefreshDatabase;

    private User $student;

    private LabSession $session;

    private const CODE = ['code' => 'public class Main { public static void main(String[] a) { System.out.println(1); } }', 'language' => 'java'];

    protected function setUp(): void
    {
        parent::setUp();

        $this->student = User::create(['name' => 'Student', 'email' => 'student@example.com', 'password' => bcrypt('password'), 'role' => 'student']);
        $instructor = User::create(['name' => 'Prof', 'email' => 'prof@example.com', 'password' => bcrypt('password'), 'role' => 'instructor']);
        $class = SchoolClass::create(['name' => 'Java 101', 'code' => 'JAVA-101', 'instructor_id' => $instructor->id]);
        $class->students()->attach($this->student->id, ['status' => 'enrolled']);
        $module = Module::create(['class_id' => $class->id, 'title' => 'Module', 'content' => 'Content']);
        $lab = Laboratory::create(['module_id' => $module->id, 'title' => 'Print', 'description' => 'Print 1.', 'time_limit' => 45, 'is_group_lab' => false, 'tasks_definition' => [['id' => 1, 'task' => 'Print 1']]]);
        $this->session = LabSession::create(['lab_id' => $lab->id, 'user_id' => $this->student->id, 'status' => 'in_progress', 'started_at' => now(), 'performance_score' => 0]);
    }

    /**
     * A grader that runs $duringGrading (to let time pass or fail) and then gives 100%.
     */
    private function grader(callable $duringGrading): void
    {
        $test = $this;
        $this->app->instance(LlmEvaluationService::class, new class($duringGrading, $test) extends LlmEvaluationService
        {
            public function __construct(private $duringGrading, private $test) {}

            public function evaluate(LabSession $session, string $code, string $language, array $diagnostics = [], array $otherFiles = []): array
            {
                ($this->duringGrading)($session);

                return ['correctness_score' => 100.0, 'tasks' => [['id' => 1, 'completed' => true]]];
            }
        });
    }

    public function test_grading_time_does_not_count_as_time_spent(): void
    {
        $this->freezeSecond();
        $submittedAt = now();

        $this->grader(function () {
            $this->travel(30)->seconds();
        });

        $this->actingAs($this->student)->postJson("/api/v1/sessions/{$this->session->id}/submit", self::CODE)
            ->assertOk();

        $this->assertTrue($this->session->fresh()->ended_at->eq($submittedAt));
    }

    public function test_the_code_is_kept_when_grading_dies(): void
    {
        $this->grader(function () {
            throw new \RuntimeException('Function timed out');
        });

        $this->actingAs($this->student)->postJson("/api/v1/sessions/{$this->session->id}/submit", self::CODE)->assertStatus(500);

        $session = $this->session->fresh();
        $this->assertSame(self::CODE['code'], $session->submitted_code);
        $this->assertSame('in_progress', $session->status);
    }

    public function test_one_claude_attempt_fits_in_the_function_limit(): void
    {
        $service = new class extends LlmEvaluationService
        {
            public function transport()
            {
                return $this->graderTransport();
            }
        };

        $timeout = $service->transport()->getConfig('timeout');
        $this->assertLessThanOrEqual(40, $timeout);
    }
}
