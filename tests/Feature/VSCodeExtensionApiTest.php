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
     * Test check progress with empty code returns 0% score and no completed tasks.
     */
    public function test_check_progress_with_empty_code_returns_zero_score(): void
    {
        $session = LabSession::create([
            'lab_id' => $this->laboratory->id,
            'user_id' => $this->student->id,
            'status' => 'in_progress',
            'started_at' => now(),
        ]);

        $response = $this->actingAs($this->student)
            ->postJson("/api/v1/sessions/{$session->id}/check-progress", [
                'code' => '   ',
                'language' => 'java',
            ]);

        $response->assertStatus(200);
        $this->assertEmpty($response->json('completed_tasks'));
        $this->assertEquals(0, $response->json('evaluation.correctness_score'));
    }

    /**
     * Test check progress with markdown/documentation returns 0% score and no completed tasks.
     */
    public function test_check_progress_with_markdown_document_returns_zero_score(): void
    {
        $session = LabSession::create([
            'lab_id' => $this->laboratory->id,
            'user_id' => $this->student->id,
            'status' => 'in_progress',
            'started_at' => now(),
        ]);

        $markdownContent = <<<MD
# TODO List
- [ ] Implement pipe IPC
- [ ] Fork child process
MD;

        $response = $this->actingAs($this->student)
            ->postJson("/api/v1/sessions/{$session->id}/check-progress", [
                'code' => $markdownContent,
                'language' => 'markdown',
            ]);

        $response->assertStatus(200);
        $this->assertEmpty($response->json('completed_tasks'));
        $this->assertEquals(0, $response->json('evaluation.correctness_score'));
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

    /**
     * Test retrieving session info includes starter files manifest.
     */
    public function test_session_info_includes_starter_files(): void
    {
        $this->laboratory->update([
            'starter_files' => [
                ['name' => 'TaskManager.java', 'content' => '// Primary', 'is_primary' => true, 'is_readonly' => false],
                ['name' => 'Task.java', 'content' => '// Model', 'is_primary' => false, 'is_readonly' => false],
            ]
        ]);

        $session = LabSession::create([
            'lab_id' => $this->laboratory->id,
            'user_id' => $this->student->id,
            'status' => 'in_progress',
            'started_at' => now(),
        ]);

        $response = $this->actingAs($this->student)
            ->getJson("/api/v1/sessions/{$session->id}");

        $response->assertStatus(200);
        $files = $response->json('laboratory.starter_files');
        $this->assertCount(2, $files);
        $this->assertEquals('TaskManager.java', $files[0]['name']);
        $this->assertTrue($files[0]['is_primary']);
    }

    /**
     * Test recording line diff updates diff_stats and code_contributions.
     */
    public function test_can_record_line_diff(): void
    {
        $session = LabSession::create([
            'lab_id' => $this->laboratory->id,
            'user_id' => $this->student->id,
            'status' => 'in_progress',
            'started_at' => now(),
        ]);

        $response = $this->actingAs($this->student)
            ->postJson("/api/v1/sessions/{$session->id}/diff", [
                'lines_added' => 42,
                'lines_deleted' => 10,
                'lines_modified' => 5,
                'files' => [
                    ['name' => 'TaskManager.java', 'added' => 42, 'deleted' => 10]
                ]
            ]);

        $response->assertStatus(200);
        $response->assertJsonFragment(['status' => 'success']);
        $this->assertEquals(42, $response->json('diff_stats.lines_added'));
        $this->assertEquals(10, $response->json('diff_stats.lines_deleted'));

        $fresh = $session->fresh();
        $this->assertEquals(42, $fresh->diff_stats['lines_added']);
        $this->assertNotEmpty($fresh->code_contributions);
    }

    /**
     * Test ephemeral team chat send and retrieve.
     */
    public function test_team_chat_send_and_retrieve(): void
    {
        $session = LabSession::create([
            'lab_id' => $this->laboratory->id,
            'user_id' => $this->student->id,
            'status' => 'in_progress',
            'started_at' => now(),
        ]);

        // Send chat message
        $sendResponse = $this->actingAs($this->student)
            ->postJson("/api/v1/sessions/{$session->id}/chat", [
                'message' => 'Hey team, I implemented the exception class!',
                'code_snippet' => 'class InvalidAgeException extends Exception {}',
            ]);

        $sendResponse->assertStatus(200);
        $sendResponse->assertJsonFragment(['status' => 'success']);
        $this->assertEquals('Hey team, I implemented the exception class!', $sendResponse->json('chat.message'));

        // Retrieve messages
        $getResponse = $this->actingAs($this->student)
            ->getJson("/api/v1/sessions/{$session->id}/chat");

        $getResponse->assertStatus(200);
        $this->assertCount(1, $getResponse->json('chats'));
        $this->assertEquals('Hey team, I implemented the exception class!', $getResponse->json('chats.0.message'));
    }
}
