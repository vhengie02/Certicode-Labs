<?php

namespace Tests\Feature;

use App\Models\Laboratory;
use App\Models\LabSession;
use App\Models\SchoolClass;
use App\Models\Module;
use App\Models\Group;
use App\Models\User;
use App\Models\Competency;
use App\Models\StudentCompetency;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AiGradeSummaryAndOverrideTest extends TestCase
{
    use RefreshDatabase;

    protected User $instructor;
    protected User $student;
    protected User $student2;
    protected Laboratory $lab;
    protected LabSession $session;

    protected function setUp(): void
    {
        parent::setUp();

        $this->instructor = User::create([
            'name' => 'Prof. Charles Xavier',
            'email' => 'charles@university.test',
            'password' => bcrypt('password'),
            'role' => 'instructor',
        ]);

        $this->student = User::create([
            'name' => 'Peter Parker',
            'email' => 'peter@university.test',
            'password' => bcrypt('password'),
            'role' => 'student',
        ]);

        $this->student2 = User::create([
            'name' => 'Gwen Stacy',
            'email' => 'gwen@university.test',
            'password' => bcrypt('password'),
            'role' => 'student',
        ]);

        $class = SchoolClass::create([
            'name' => 'Advanced Java Programming',
            'code' => 'CS301',
            'instructor_id' => $this->instructor->id,
        ]);

        $module = Module::create([
            'class_id' => $class->id,
            'title' => 'OOP & Custom Exceptions',
            'order_index' => 1,
        ]);

        $this->lab = Laboratory::create([
            'module_id' => $module->id,
            'title' => 'Custom Exception Lab',
            'description' => 'Implement InvalidAgeException and Student encapsulation.',
            'language' => 'java',
            'tasks_definition' => [
                [
                    'id' => 1,
                    'task' => 'Implement InvalidAgeException extending Exception',
                    'command' => 'regex:class\s+InvalidAgeException\s+extends\s+Exception',
                ],
                [
                    'id' => 2,
                    'task' => 'Define class Student with private fields',
                    'command' => 'regex:class\s+Student',
                ],
            ],
        ]);

        $this->session = LabSession::create([
            'lab_id' => $this->lab->id,
            'user_id' => $this->student->id,
            'status' => 'in_progress',
            'started_at' => now(),
        ]);
    }

    /**
     * Test 1: Submitting a session generates and persists ai_grade_summary in the database.
     */
    public function test_submission_generates_and_persists_ai_grade_summary_in_database(): void
    {
        $code = <<<'JAVA'
public class InvalidAgeException extends Exception {
    public InvalidAgeException(String msg) { super(msg); }
}

public class Student {
    private String name;
    private int age;
}
JAVA;

        $response = $this->actingAs($this->student)->postJson("/api/v1/sessions/{$this->session->id}/submit", [
            'code' => $code,
            'language' => 'java',
        ]);

        $response->assertOk()
            ->assertJsonPath('status', 'success');

        $this->session->refresh();

        $this->assertNotNull($this->session->ai_grade_summary);
        $summary = $this->session->ai_grade_summary;

        $this->assertIsArray($summary);
        $this->assertArrayHasKey('competencies', $summary);
        $this->assertArrayHasKey('test_cases_passed', $summary);
        $this->assertArrayHasKey('test_cases_total', $summary);
        $this->assertArrayHasKey('code_quality_notes', $summary);
        $this->assertArrayHasKey('summary', $summary);

        $this->assertEquals(2, $summary['test_cases_passed']);
        $this->assertEquals(2, $summary['test_cases_total']);
        $this->assertNotEmpty($summary['summary']);
    }

    /**
     * Test 2: Student response omits instructor-only AI grade summary and internal notes.
     */
    public function test_student_response_omits_ai_grade_summary(): void
    {
        $code = <<<'JAVA'
public class InvalidAgeException extends Exception {}
JAVA;

        $response = $this->actingAs($this->student)->postJson("/api/v1/sessions/{$this->session->id}/submit", [
            'code' => $code,
            'language' => 'java',
        ]);

        $response->assertOk();

        // Student-facing payload must NOT expose ai_grade_summary
        $response->assertJsonMissing(['ai_grade_summary']);
        $evaluation = $response->json('evaluation');
        $this->assertArrayNotHasKey('ai_grade_summary', $evaluation);
        $this->assertArrayNotHasKey('competencies', $evaluation);
        $this->assertArrayNotHasKey('code_quality_notes', $evaluation);
        $this->assertArrayNotHasKey('summary', $evaluation);

        // Also test check-progress
        $progressResponse = $this->actingAs($this->student)->postJson("/api/v1/sessions/{$this->session->id}/check-progress", [
            'code' => $code,
            'language' => 'java',
        ]);

        $progressResponse->assertOk();
        $progressEval = $progressResponse->json('evaluation');
        $this->assertArrayNotHasKey('ai_grade_summary', $progressEval);
        $this->assertArrayNotHasKey('competencies', $progressEval);
        $this->assertArrayNotHasKey('code_quality_notes', $progressEval);
        $this->assertArrayNotHasKey('summary', $progressEval);
    }

    /**
     * Test 3: Instructor can view ai_grade_summary in live monitoring view and telemetry stream.
     */
    public function test_instructor_can_view_ai_grade_summary_in_monitoring_view_and_stream(): void
    {
        $this->session->update([
            'status' => 'completed',
            'performance_score' => 85.0,
            'ai_grade_summary' => [
                'competencies' => [
                    'oop_inheritance' => ['passed' => true, 'reason' => 'Class extends Exception properly.'],
                ],
                'test_cases_passed' => 2,
                'test_cases_total' => 2,
                'code_quality_notes' => 'Clean naming conventions.',
                'summary' => 'Student successfully completed all checklist requirements with strong encapsulation.',
            ],
        ]);

        // HTML monitoring dashboard
        $viewResponse = $this->actingAs($this->instructor)->get("/laboratories/{$this->lab->id}/monitoring");
        $viewResponse->assertOk()
            ->assertSee('AI Grade Assessment &amp; Explanation', false)
            ->assertSee('Grade: 85%');

        // JSON telemetry stream
        $dataResponse = $this->actingAs($this->instructor)->getJson("/laboratories/{$this->lab->id}/monitoring/data");
        $dataResponse->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('sessions.0.id', $this->session->id)
            ->assertJsonPath('sessions.0.performance_score', 85)
            ->assertJsonPath('sessions.0.ai_grade_summary.summary', 'Student successfully completed all checklist requirements with strong encapsulation.');
    }

    /**
     * Test 4: Instructor can override grade; AI score and override persist side-by-side.
     */
    public function test_instructor_can_override_grade_and_both_records_persist_side_by_side(): void
    {
        $this->session->update([
            'status' => 'completed',
            'performance_score' => 60.0,
            'ai_grade_summary' => [
                'competencies' => [
                    'oop_inheritance' => ['passed' => false, 'reason' => 'Missing custom message constructor.'],
                ],
                'test_cases_passed' => 1,
                'test_cases_total' => 2,
                'code_quality_notes' => 'Partial implementation.',
                'summary' => 'Initial AI assessment flagged missing constructor.',
            ],
        ]);

        $overrideResponse = $this->actingAs($this->instructor)->postJson("/instructor/sessions/{$this->session->id}/override-grade", [
            'override_score' => 90.0,
            'override_reason' => 'Partial credit granted: Student explained logic during office hours.',
        ]);

        $overrideResponse->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('session.performance_score', 60)
            ->assertJsonPath('session.instructor_grade_override', 90)
            ->assertJsonPath('session.effective_score', 90)
            ->assertJsonPath('session.is_overridden', true);

        $this->session->refresh();

        // Check side-by-side audit trail preservation
        $this->assertEquals(60.0, $this->session->performance_score); // Original AI grade preserved
        $this->assertEquals(90.0, $this->session->instructor_grade_override);
        $this->assertEquals(90.0, $this->session->effective_score);
        $this->assertTrue($this->session->isGradeOverridden());
        $this->assertEquals('Partial credit granted: Student explained logic during office hours.', $this->session->instructor_override_reason);
        $this->assertEquals($this->instructor->id, $this->session->overridden_by);
        $this->assertNotNull($this->session->instructor_overridden_at);

        // Verify StudentCompetency updated to effective override score
        $competency = Competency::where('code', 'COMP-JAVA-01')->first();
        $this->assertNotNull($competency);
        $studentComp = StudentCompetency::where('user_id', $this->student->id)
            ->where('competency_id', $competency->id)
            ->first();
        $this->assertNotNull($studentComp);
        $this->assertEquals(90.0, (float) $studentComp->score_achieved);
    }

    /**
     * Test 5: Students cannot override grades.
     */
    public function test_students_and_unauthorized_users_cannot_override_grades(): void
    {
        $this->session->update([
            'status' => 'completed',
            'performance_score' => 50.0,
        ]);

        // Student attempt
        $studentAttempt = $this->actingAs($this->student)->postJson("/instructor/sessions/{$this->session->id}/override-grade", [
            'override_score' => 100.0,
            'override_reason' => 'Self override',
        ]);
        $studentAttempt->assertForbidden();

        // Guest attempt
        auth()->logout();
        $guestAttempt = $this->postJson("/instructor/sessions/{$this->session->id}/override-grade", [
            'override_score' => 100.0,
        ]);
        $guestAttempt->assertUnauthorized();
    }

    /**
     * Test 6: Team lab evaluates team submission as a single combined unit.
     */
    public function test_team_lab_session_evaluated_as_single_unit(): void
    {
        $group = Group::create([
            'name' => 'Team Alpha',
            'lab_id' => $this->lab->id,
        ]);

        $teamSession = LabSession::create([
            'lab_id' => $this->lab->id,
            'user_id' => $this->student->id,
            'group_id' => $group->id,
            'status' => 'in_progress',
            'started_at' => now(),
        ]);

        $code = <<<'JAVA'
public class InvalidAgeException extends Exception {}
public class Student { private String name; private int age; }
JAVA;

        $response = $this->actingAs($this->student)->postJson("/api/v1/sessions/{$teamSession->id}/submit", [
            'code' => $code,
            'language' => 'java',
        ]);

        $response->assertOk();
        $teamSession->refresh();

        $this->assertNotNull($teamSession->ai_grade_summary);
        // The summary evaluates the team submission without breaking down individual teammate stats
        $summary = $teamSession->ai_grade_summary;
        $this->assertArrayHasKey('summary', $summary);
        $this->assertArrayNotHasKey('individual_contributions', $summary);
    }
}
