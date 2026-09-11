<?php

namespace Tests\Feature;

use App\Models\Laboratory;
use App\Models\User;
use App\Models\LabSession;
use App\Models\Competency;
use App\Models\StudentCompetency;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VSCodeExtensionApiTest extends TestCase
{
    use RefreshDatabase;

    protected User $student;
    protected Laboratory $laboratory;

    protected function setUp(): void
    {
        parent::setUp();

        // Create users
        $this->student = User::create([
            'name' => 'Jane Student',
            'email' => 'student@example.com',
            'password' => bcrypt('password'),
            'role' => 'student',
        ]);

        // Create a Java laboratory exercise
        $this->laboratory = Laboratory::create([
            'title' => 'Java Exceptions & OOP Test',
            'description' => 'Test description.',
            'time_limit' => 30,
            'is_group_lab' => false,
            'tasks_definition' => [
                [
                    'id' => 1,
                    'task' => 'Create custom InvalidAgeException class extending Exception',
                    'command' => 'regex:class\s+InvalidAgeException\s+extends\s+Exception',
                ],
                [
                    'id' => 2,
                    'task' => 'Create Student class with private name and age fields',
                    'command' => 'regex:private\s+String\s+name|private\s+int\s+age',
                ],
            ],
            'reference_solution' => '// Solution code',
            'rubric' => 'Rubric text',
            'test_cases' => [
                ['input' => '', 'expected' => 'expected output']
            ]
        ]);
    }

    /**
     * Test retrieving active session info via API.
     */
    public function test_can_retrieve_session_info(): void
    {
        $session = LabSession::create([
            'lab_id' => $this->laboratory->id,
            'user_id' => $this->student->id,
            'status' => 'in_progress',
            'started_at' => now(),
        ]);

        $response = $this->actingAs($this->student)
            ->getJson("/api/v1/sessions/{$session->id}");

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'session_id',
            'status',
            'elapsed_seconds',
            'time_remaining_seconds',
            'completed_tasks',
            'laboratory' => [
                'title',
                'description',
                'tasks_definition',
            ]
        ]);
    }

    /**
     * Test check progress (AI task evaluation) with invalid code.
     */
    public function test_check_progress_with_invalid_code(): void
    {
        $session = LabSession::create([
            'lab_id' => $this->laboratory->id,
            'user_id' => $this->student->id,
            'status' => 'in_progress',
            'started_at' => now(),
        ]);

        $response = $this->actingAs($this->student)
            ->postJson("/api/v1/sessions/{$session->id}/check-progress", [
                'code' => 'public class Temp {}',
                'language' => 'java',
            ]);

        $response->assertStatus(200);
        $response->assertJsonFragment([
            'status' => 'success',
        ]);
        
        $this->assertEmpty($response->json('completed_tasks'));
    }

    /**
     * Test check progress (AI task evaluation) with valid code.
     */
    public function test_check_progress_with_valid_code(): void
    {
        $session = LabSession::create([
            'lab_id' => $this->laboratory->id,
            'user_id' => $this->student->id,
            'status' => 'in_progress',
            'started_at' => now(),
        ]);

        $validJavaCode = <<<JAVA
class InvalidAgeException extends Exception {}
class Student {
    private String name;
    private int age;
}
JAVA;

        $response = $this->actingAs($this->student)
            ->postJson("/api/v1/sessions/{$session->id}/check-progress", [
                'code' => $validJavaCode,
                'language' => 'java',
            ]);

        $response->assertStatus(200);
        $this->assertContains(1, $response->json('completed_tasks'));
        $this->assertContains(2, $response->json('completed_tasks'));
    }

    /**
     * Test final submission executes code and maps competency.
     */
    public function test_submit_session_completes_and_maps_competency(): void
    {
        $session = LabSession::create([
            'lab_id' => $this->laboratory->id,
            'user_id' => $this->student->id,
            'status' => 'in_progress',
            'started_at' => now(),
        ]);

        $validJavaCode = <<<JAVA
class InvalidAgeException extends Exception {}
class Student {
    private String name;
    private int age;
}
JAVA;

        $response = $this->actingAs($this->student)
            ->postJson("/api/v1/sessions/{$session->id}/submit", [
                'code' => $validJavaCode,
                'language' => 'java',
            ]);

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'status',
            'completed_tasks',
            'performance_score',
            'execution',
            'evaluation',
        ]);

        // Assert session status is completed
        $this->assertEquals('completed', $session->fresh()->status);

        // Assert competency was registered
        $this->assertDatabaseHas('student_competencies', [
            'user_id' => $this->student->id,
            'score_achieved' => 100.0,
        ]);
    }
}
