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
     * Closing VS Code reports a disconnect, so the browser page leaves the lab.
     * A later extension ping (VS Code reopened) connects again.
     */
    public function test_vscode_disconnect_marks_session_as_closed_until_next_ping(): void
    {
        $session = LabSession::create([
            'lab_id' => $this->laboratory->id,
            'user_id' => $this->student->id,
            'status' => 'in_progress',
            'started_at' => now(),
        ]);

        $this->actingAs($this->student)
            ->postJson("/api/v1/sessions/{$session->id}/ping", ['source' => 'vscode'])
            ->assertOk();

        $this->getJson("/v1/sessions/{$session->id}")
            ->assertOk()
            ->assertJson(['vscode_connected' => true, 'vscode_closed' => false]);

        $this->postJson("/api/v1/sessions/{$session->id}/vscode-disconnect", ['source' => 'vscode'])
            ->assertOk();

        $this->assertDatabaseHas('telemetry_logs', [
            'lab_session_id' => $session->id,
            'event_type' => 'vscode_disconnected',
        ]);
        $this->getJson("/v1/sessions/{$session->id}")
            ->assertOk()
            ->assertJson(['vscode_connected' => false, 'vscode_closed' => true]);

        $this->postJson("/api/v1/sessions/{$session->id}/ping", ['source' => 'vscode'])
            ->assertOk();

        $this->getJson("/v1/sessions/{$session->id}")
            ->assertOk()
            ->assertJson(['vscode_connected' => true, 'vscode_closed' => false]);
    }

    /**
     * The extension reports its version on every request; old builds are flagged as outdated.
     */
    public function test_extension_version_is_checked_against_minimum(): void
    {
        config(['services.vscode_extension.min_version' => '1.6.0']);
        $session = LabSession::create([
            'lab_id' => $this->laboratory->id,
            'user_id' => $this->student->id,
            'status' => 'in_progress',
            'started_at' => now(),
        ]);
        $this->actingAs($this->student);

        // Nothing known until the extension pings.
        $this->getJson("/v1/sessions/{$session->id}")
            ->assertJson(['extension_outdated' => null, 'extension_min_version' => '1.6.0']);

        // Builds from before version reporting send no header.
        $this->postJson("/api/v1/sessions/{$session->id}/ping", ['source' => 'vscode'])
            ->assertJson(['extension_outdated' => true]);

        $this->withHeaders(['X-Extension-Version' => '1.5.0'])
            ->postJson("/api/v1/sessions/{$session->id}/ping", ['source' => 'vscode'])
            ->assertJson(['extension_outdated' => true, 'extension_version' => '1.5.0']);

        $this->withHeaders(['X-Extension-Version' => '1.6.0', 'X-Client' => 'vscode'])
            ->getJson("/api/v1/sessions/{$session->id}")
            ->assertJson(['extension_outdated' => false, 'extension_version' => '1.6.0']);

        // The browser page reads the version the extension reported.
        $this->withHeaders(['X-Extension-Version' => '', 'X-Client' => ''])
            ->getJson("/v1/sessions/{$session->id}")
            ->assertJson(['extension_outdated' => false, 'extension_version' => '1.6.0']);
    }

    /**
     * Browser pings keep last_ping_at fresh, so they must not count as VS Code being open.
     */
    public function test_browser_ping_does_not_count_as_vscode_connected(): void
    {
        $session = LabSession::create([
            'lab_id' => $this->laboratory->id,
            'user_id' => $this->student->id,
            'status' => 'in_progress',
            'started_at' => now(),
            'wpm' => 40,
            'keystroke_count' => 200,
        ]);

        $this->actingAs($this->student)
            ->postJson("/v1/sessions/{$session->id}/ping", ['face_count' => 1])
            ->assertOk();

        $this->getJson("/v1/sessions/{$session->id}")
            ->assertOk()
            ->assertJson(['vscode_connected' => false]);
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
     * Test check progress with syntax error (like trailing garbage after semicolon) returns 0% score and all pending tasks.
     */
    public function test_check_progress_with_syntax_error_returns_zero_score_and_pending_tasks(): void
    {
        $session = LabSession::create([
            'lab_id' => $this->laboratory->id,
            'user_id' => $this->student->id,
            'status' => 'in_progress',
            'started_at' => now(),
        ]);

        $brokenCode = <<<JAVA
public class Repository<T extends Entity<K>, K> {
    public void save(T entity) {
    }
    public Optional<T> findById(K id) {
        return Optional.ofNullable(storage.get(id));
    }
    public List<T> filter(Predicate<T> predicate) {
        List<T> result = new ArrayList<>();
        return result;aaaaaaaaaaaaaaaaaa
    }
}
JAVA;

        $response = $this->actingAs($this->student)
            ->postJson("/api/v1/sessions/{$session->id}/check-progress", [
                'code' => $brokenCode,
                'language' => 'java',
            ]);

        $response->assertStatus(200);
        $this->assertEmpty($response->json('completed_tasks'));
        $this->assertEquals(0, $response->json('evaluation.correctness_score'));
        $this->assertStringContainsString('Syntax errors detected', $response->json('evaluation.overall_feedback'));
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
     * Errors in files outside the submission (e.g. VS Code settings) must not fail the syntax gate.
     */
    public function test_check_progress_ignores_diagnostics_from_unsubmitted_files(): void
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
                'files' => [['name' => 'Student.java', 'content' => $validJavaCode, 'is_primary' => true]],
                'language' => 'java',
                'diagnostics' => [
                    ['file' => '.vscode/settings.json', 'line' => 3, 'message' => 'Expected comma', 'source' => 'json'],
                ],
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
        // Grading credits exactly the competencies the lab is tagged with
        $competency = \App\Models\Competency::create(['code' => 'JAVA-EXC-01', 'name' => 'Custom exceptions in Java']);
        $untagged = \App\Models\Competency::create(['code' => 'SQL-01', 'name' => 'SQL basics']);
        $this->laboratory->competencies()->attach($competency->id);

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

        // The tagged competency is credited with the grade; the untagged one isn't touched
        $this->assertDatabaseHas('student_competencies', [
            'user_id' => $this->student->id,
            'competency_id' => $competency->id,
            'score_achieved' => 100.0,
        ]);
        $this->assertDatabaseMissing('student_competencies', ['competency_id' => $untagged->id]);
    }

    /**
     * Test submission returns a summary; an open lab has no leaderboard place.
     */
    public function test_submit_returns_summary_without_place_for_open_lab(): void
    {
        $session = LabSession::create([
            'lab_id' => $this->laboratory->id,
            'user_id' => $this->student->id,
            'status' => 'in_progress',
            'started_at' => now()->subMinutes(10),
            'keystroke_count' => 1000,
        ]);

        $response = $this->actingAs($this->student)
            ->postJson("/api/v1/sessions/{$session->id}/submit", [
                'code' => 'class InvalidAgeException extends Exception {}',
                'language' => 'java',
            ]);

        $response->assertOk();
        $summary = $response->json('summary');
        $this->assertEqualsWithDelta(600, $summary['time_spent_seconds'], 5);
        $this->assertSame(2, $summary['tasks_total']);
        $this->assertSame(20, $summary['wpm']);
        $this->assertFalse($summary['is_live']);
        $this->assertNull($summary['place']);
    }

    /**
     * Test a live lab submission reports the student's leaderboard place.
     */
    public function test_submit_returns_place_for_live_lab(): void
    {
        $this->laboratory->update([
            'availability_mode' => 'live',
            'live_status' => 'active',
            'live_started_at' => now()->subMinutes(5),
            'live_duration_minutes' => 60,
        ]);

        // A classmate already finished both tasks, so this student can't beat them
        $rival = User::create([
            'name' => 'Rival Student',
            'email' => 'rival@example.com',
            'password' => bcrypt('password'),
            'role' => 'student',
        ]);
        LabSession::create([
            'lab_id' => $this->laboratory->id,
            'user_id' => $rival->id,
            'status' => 'completed',
            'started_at' => now()->subMinutes(5),
            'ended_at' => now()->subMinutes(4),
            'completed_tasks' => [1, 2],
        ]);

        $session = LabSession::create([
            'lab_id' => $this->laboratory->id,
            'user_id' => $this->student->id,
            'status' => 'in_progress',
            'started_at' => now()->subMinutes(5),
        ]);

        $response = $this->actingAs($this->student)
            ->postJson("/api/v1/sessions/{$session->id}/submit", [
                'code' => '// nothing yet',
                'language' => 'java',
            ]);

        $response->assertOk();
        $this->assertTrue($response->json('summary.is_live'));
        $this->assertSame(2, $response->json('summary.place'));
        $this->assertSame(2, $response->json('summary.participants'));
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
