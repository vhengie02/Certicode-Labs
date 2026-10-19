<?php

namespace Tests\Feature;

use App\Models\Laboratory;
use App\Models\LabSession;
use App\Models\Module;
use App\Models\SchoolClass;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The reference solution, rubric, test cases and per-task checks are the grading key.
 * Nothing a student can call may return them.
 */
class LabAnswerKeyExposureTest extends TestCase
{
    use RefreshDatabase;

    private const SOLUTION = 'class SECRET_REFERENCE_SOLUTION {}';

    private const RUBRIC = 'SECRET_RUBRIC_TEXT';

    private const EXPECTED = 'SECRET_EXPECTED_OUTPUT';

    private const CHECK = 'regex:SECRET_TASK_CHECK';

    private User $student;

    private Laboratory $lab;

    protected function setUp(): void
    {
        parent::setUp();

        $instructor = User::create(['name' => 'Prof', 'email' => 'prof@example.com', 'password' => bcrypt('password'), 'role' => 'instructor']);
        $this->student = User::create(['name' => 'Ana', 'email' => 'ana@example.com', 'password' => bcrypt('password'), 'role' => 'student']);

        $class = SchoolClass::create(['name' => 'CS 101', 'code' => 'CS101', 'instructor_id' => $instructor->id]);
        $class->students()->attach([$this->student->id => ['status' => 'enrolled']]);
        $module = Module::create(['class_id' => $class->id, 'title' => 'Module 1', 'content' => 'Content', 'order_index' => 0]);

        // No starter files, as in the seeded labs: the case that used to fall back to the solution
        $this->lab = Laboratory::create([
            'module_id' => $module->id,
            'title' => 'Lab 1',
            'description' => 'Description',
            'time_limit' => 45,
            'tasks_definition' => [['id' => 1, 'task' => 'Write the class', 'command' => self::CHECK]],
            'reference_solution' => self::SOLUTION,
            'rubric' => self::RUBRIC,
            'test_cases' => [['input' => '', 'expected' => self::EXPECTED]],
        ]);
    }

    private function assertNoAnswerKey(string $content): void
    {
        foreach ([self::SOLUTION, self::RUBRIC, self::EXPECTED, 'SECRET_TASK_CHECK', 'reference_solution', '"rubric"', '"test_cases"'] as $secret) {
            $this->assertStringNotContainsString($secret, $content, "Student-facing response leaked {$secret}");
        }
    }

    public function test_start_session_response_has_no_answer_key(): void
    {
        $response = $this->actingAs($this->student)->postJson("/api/v1/labs/{$this->lab->id}/start");

        $response->assertOk()
            ->assertJsonPath('session.laboratory.title', 'Lab 1')
            ->assertJsonPath('session.laboratory.tasks_definition.0.task', 'Write the class');
        $this->assertNoAnswerKey($response->getContent());
    }

    public function test_get_session_has_no_answer_key_or_task_checks(): void
    {
        $session = LabSession::create(['lab_id' => $this->lab->id, 'user_id' => $this->student->id, 'status' => 'in_progress', 'started_at' => now()]);

        $response = $this->actingAs($this->student)->getJson("/api/v1/sessions/{$session->id}");

        $response->assertOk()
            ->assertJsonPath('laboratory.tasks_definition', [['id' => 1, 'task' => 'Write the class']])
            ->assertJsonPath('laboratory.starter_files.0.name', 'TaskManager.java');
        $this->assertNoAnswerKey($response->getContent());
    }

    public function test_starter_file_download_is_not_the_reference_solution(): void
    {
        $response = $this->actingAs($this->student)->get("/laboratories/{$this->lab->id}/starter-files/download");

        $response->assertOk();
        $this->assertStringContainsString('class TaskManager', $response->getContent());
        $this->assertNoAnswerKey($response->getContent());
    }

    public function test_serialized_laboratory_hides_grading_columns(): void
    {
        $json = json_encode($this->lab->fresh());

        foreach ([self::SOLUTION, self::RUBRIC, self::EXPECTED, 'reference_solution', '"rubric"', '"test_cases"'] as $secret) {
            $this->assertStringNotContainsString($secret, $json);
        }
    }
}
