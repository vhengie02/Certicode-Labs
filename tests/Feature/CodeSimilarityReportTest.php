<?php

namespace Tests\Feature;

use App\Models\Group;
use App\Models\Laboratory;
use App\Models\LabSession;
use App\Models\Module;
use App\Models\SchoolClass;
use App\Models\User;
use App\Services\CodeSimilarityService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The similarity scan is computed once per submit and stored, and it only compares work that
 * could have been copied: not a student's own retakes, not teammates, not the starter code.
 */
class CodeSimilarityReportTest extends TestCase
{
    use RefreshDatabase;

    private const STARTER = <<<'JAVA'
public class TaskManager {
    public static void main(String[] args) {
        System.out.println("Task manager");
    }
}
JAVA;

    // Real work: what a copy would share, well beyond the starter code
    private const SOLUTION = <<<'JAVA'
public class TaskManager {
    private java.util.List<String> tasks = new java.util.ArrayList<>();
    public void add(String task) { if (task == null || task.isEmpty()) { throw new IllegalArgumentException("empty"); } tasks.add(task); }
    public boolean remove(int index) { if (index < 0 || index >= tasks.size()) { return false; } tasks.remove(index); return true; }
    public int count() { int total = 0; for (String t : tasks) { if (!t.isBlank()) { total++; } } return total; }
    public static void main(String[] args) {
        System.out.println("Task manager");
    }
}
JAVA;

    private User $instructor;
    private SchoolClass $class;
    private Laboratory $lab;

    protected function setUp(): void
    {
        parent::setUp();

        $this->instructor = User::create(['name' => 'Prof', 'email' => 'prof@example.com', 'password' => bcrypt('password'), 'role' => 'instructor']);
        $this->class = SchoolClass::create(['name' => 'CS 101', 'code' => 'CS101', 'instructor_id' => $this->instructor->id]);
        $module = Module::create(['class_id' => $this->class->id, 'title' => 'Module 1', 'content' => 'Content', 'order_index' => 0]);
        $this->lab = Laboratory::create([
            'module_id' => $module->id,
            'title' => 'Tasks',
            'description' => 'Build a task manager',
            'time_limit' => 0,
            'max_attempts' => 0,
            'tasks_definition' => [['id' => 1, 'task' => 'Add tasks']],
            'starter_files' => [['name' => 'TaskManager.java', 'content' => self::STARTER, 'is_primary' => true, 'is_readonly' => false]],
        ]);
    }

    private function student(string $name): User
    {
        $user = User::create(['name' => $name, 'email' => strtolower($name) . '@example.com', 'password' => bcrypt('password'), 'role' => 'student']);
        $this->class->students()->attach([$user->id => ['status' => 'enrolled']]);

        return $user;
    }

    private function submission(User $user, string $code, array $extra = []): LabSession
    {
        return LabSession::create(array_merge([
            'lab_id' => $this->lab->id,
            'user_id' => $user->id,
            'status' => 'completed',
            'started_at' => now()->subMinutes(10),
            'ended_at' => now(),
            'submitted_code' => $code,
        ], $extra));
    }

    private function analysis(): array
    {
        return app(CodeSimilarityService::class)->analyzeLabCohort($this->lab->id);
    }

    public function test_copied_work_is_still_flagged(): void
    {
        $this->submission($this->student('Ana'), self::SOLUTION);
        $this->submission($this->student('Ben'), str_replace('tasks', 'items', self::SOLUTION));

        $this->assertSame('high', $this->analysis()['flagged_pairs'][0]['risk'] ?? null);
    }

    public function test_a_students_own_retake_is_not_a_match(): void
    {
        $ana = $this->student('Ana');
        $this->submission($ana, self::SOLUTION);
        $this->submission($ana, self::SOLUTION);

        $analysis = $this->analysis();
        $this->assertSame(1, $analysis['total_students_with_code']);
        $this->assertSame(0, $analysis['flagged_pairs_count']);
    }

    public function test_teammates_are_not_compared(): void
    {
        $group = Group::create(['name' => 'Team 1', 'lab_id' => $this->lab->id]);
        $this->submission($this->student('Ana'), self::SOLUTION, ['group_id' => $group->id]);
        $this->submission($this->student('Ben'), self::SOLUTION, ['group_id' => $group->id]);

        $this->assertSame(0, $this->analysis()['flagged_pairs_count']);
    }

    public function test_untouched_starter_code_is_not_a_match(): void
    {
        $this->submission($this->student('Ana'), self::STARTER);
        $this->submission($this->student('Ben'), self::STARTER, ['status' => 'in_progress', 'ended_at' => null]);

        $this->assertSame(0, $this->analysis()['flagged_pairs_count']);
    }

    public function test_monitoring_page_reads_the_stored_report_instead_of_comparing_again(): void
    {
        $this->submission($this->student('Ana'), self::SOLUTION);
        $this->submission($this->student('Ben'), self::SOLUTION);

        $counter = new class extends CodeSimilarityService {
            public int $runs = 0;
            public function analyzeLabCohort(int $labId, float $highRiskThreshold = 75.0, float $mediumRiskThreshold = 50.0): array
            {
                $this->runs++;
                return parent::analyzeLabCohort($labId, $highRiskThreshold, $mediumRiskThreshold);
            }
        };
        $this->app->instance(CodeSimilarityService::class, $counter);

        $url = route('classes.telemetry', ['class_id' => $this->class->id, 'lab' => $this->lab->id]);
        $this->actingAs($this->instructor)->get($url)->assertOk()->assertSee('Similar submissions');
        $this->actingAs($this->instructor)->get($url)->assertOk();
        $this->actingAs($this->instructor)->get($url)->assertOk();

        $this->assertSame(1, $counter->runs, 'Each monitoring page view compared every pair again');
    }

    public function test_submitting_stores_a_fresh_report(): void
    {
        $this->submission($this->student('Ana'), self::SOLUTION);
        $ben = $this->student('Ben');
        $session = $this->submission($ben, '', ['status' => 'in_progress', 'ended_at' => null]);

        $this->actingAs($ben)->postJson("/api/v1/sessions/{$session->id}/submit", [
            'code' => self::SOLUTION,
            'language' => 'java',
        ])->assertOk();

        $report = \App\Models\LabSimilarityReport::where('lab_id', $this->lab->id)->firstOrFail();
        $this->assertSame(2, $report->analysis['total_students_with_code']);
        $this->assertSame(1, $report->analysis['flagged_pairs_count']);
    }
}
