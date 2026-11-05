<?php

namespace Tests\Feature;

use App\Models\Certificate;
use App\Models\Laboratory;
use App\Models\LabSession;
use App\Models\Module;
use App\Models\SchoolClass;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CertificateLabPassScoreTest extends TestCase
{
    use RefreshDatabase;

    private User $instructor;

    private User $student;

    private SchoolClass $class;

    /** @var list<Laboratory> */
    private array $labs = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->instructor = User::create(['name' => 'Prof', 'email' => 'prof@example.com', 'password' => bcrypt('password'), 'role' => 'instructor']);
        $this->student = User::create(['name' => 'Sam Student', 'email' => 'sam@example.com', 'password' => bcrypt('password'), 'role' => 'student']);
        $this->class = SchoolClass::create(['name' => 'Java 101', 'code' => 'JAVA-101', 'instructor_id' => $this->instructor->id, 'passing_threshold' => 50, 'lab_pass_score' => 70]);
        $this->class->students()->attach($this->student->id, ['status' => 'enrolled']);

        $module = Module::create(['class_id' => $this->class->id, 'title' => 'Module', 'content' => 'x']);
        foreach (['Loops', 'Arrays'] as $title) {
            $this->labs[] = Laboratory::create(['module_id' => $module->id, 'title' => $title, 'description' => 'x', 'time_limit' => 30, 'tasks_definition' => []]);
        }
    }

    private function attempt(Laboratory $lab, array $attributes = []): LabSession
    {
        return LabSession::create($attributes + [
            'lab_id' => $lab->id,
            'user_id' => $this->student->id,
            'status' => 'completed',
            'started_at' => now()->subHour(),
            'ended_at' => now(),
        ]);
    }

    private function issue(): array
    {
        return Certificate::issueFor($this->class->fresh(), $this->student);
    }

    public function test_finishing_labs_with_failing_scores_earns_no_certificate(): void
    {
        $this->attempt($this->labs[0], ['performance_score' => 10]);
        $this->attempt($this->labs[1], ['performance_score' => 0]);

        $result = $this->issue();

        $this->assertNull($result['certificate']);
        $this->assertSame(0, $result['progress']['passed']);
    }

    public function test_enough_labs_at_the_pass_score_earn_the_certificate(): void
    {
        $this->attempt($this->labs[0], ['performance_score' => 70]);

        $this->assertNotNull($this->issue()['certificate']);
    }

    public function test_a_score_just_below_the_pass_score_does_not_count(): void
    {
        $this->attempt($this->labs[0], ['performance_score' => 69.9]);

        $this->assertNull($this->issue()['certificate']);
    }

    public function test_the_best_attempt_counts(): void
    {
        $this->attempt($this->labs[0], ['performance_score' => 20]);
        $this->attempt($this->labs[0], ['performance_score' => 90]);

        $this->assertSame(1, $this->issue()['progress']['passed']);
    }

    public function test_an_unfinished_attempt_does_not_count(): void
    {
        $this->attempt($this->labs[0], ['performance_score' => 95, 'status' => 'in_progress', 'ended_at' => null]);

        $this->assertSame(0, $this->issue()['progress']['passed']);
    }

    public function test_instructor_override_decides_the_score_both_ways(): void
    {
        $raised = $this->attempt($this->labs[0], ['performance_score' => 30, 'instructor_grade_override' => 80]);
        $this->assertSame(1, $this->issue()['progress']['passed']);

        $raised->update(['performance_score' => 90, 'instructor_grade_override' => 40]);
        $this->assertSame(0, $this->issue()['progress']['passed']);
    }

    public function test_a_provisional_grade_waits_for_the_instructor(): void
    {
        $attempt = $this->attempt($this->labs[0], ['performance_score' => 100, 'ai_grade_summary' => ['needs_review' => true]]);

        $result = $this->issue();
        $this->assertNull($result['certificate']);
        $this->assertSame(1, $result['progress']['under_review']);

        // Saving the grade unchanged is how an instructor confirms it
        $attempt->update(['instructor_grade_override' => 100]);

        $result = $this->issue();
        $this->assertNotNull($result['certificate']);
        $this->assertSame(0, $result['progress']['under_review']);
    }

    public function test_a_passed_lab_is_not_also_under_review(): void
    {
        $this->attempt($this->labs[0], ['performance_score' => 90]);
        $this->attempt($this->labs[0], ['performance_score' => 100, 'ai_grade_summary' => ['needs_review' => true]]);

        $this->assertSame(0, $this->issue()['progress']['under_review']);
    }

    public function test_a_certificate_already_issued_is_kept_when_the_rules_are_stricter(): void
    {
        $issued = Certificate::create(['user_id' => $this->student->id, 'class_id' => $this->class->id, 'verification_code' => 'CERT-EXISTING', 'qr_code_path' => 'certificates/qr.svg', 'issued_at' => now()]);

        $result = $this->issue();

        $this->assertSame($issued->id, $result['certificate']->id);
        $this->assertFalse($result['created']);
    }

    public function test_ending_a_class_only_certifies_students_who_passed(): void
    {
        $this->attempt($this->labs[0], ['performance_score' => 10]);
        $this->attempt($this->labs[1], ['performance_score' => 10]);

        $this->assertSame(0, $this->class->fresh()->concludeAndCertify());
        $this->assertSame(0, Certificate::count());
    }

    public function test_a_refused_claim_says_what_is_missing(): void
    {
        $this->attempt($this->labs[0], ['performance_score' => 100, 'ai_grade_summary' => ['needs_review' => true]]);

        $this->actingAs($this->student)
            ->post(route('classes.claim-certificate', $this->class->id))
            ->assertSessionHas('error', "You need 70% or more on 50% of this class's labs to claim a certificate. You've passed 0 of 2. Your instructor still has to review 1 grade.");
    }

    public function test_class_page_counts_passed_labs_and_shows_grades_under_review(): void
    {
        $this->attempt($this->labs[0], ['performance_score' => 90]);
        $this->attempt($this->labs[1], ['performance_score' => 100, 'ai_grade_summary' => ['needs_review' => true]]);

        $this->actingAs($this->student)->get("/classes/{$this->class->id}")
            ->assertOk()
            ->assertSee('1 of 2 labs passed at 70% or more')
            ->assertSee('1 grade waiting for your instructor');
    }

    public function test_instructors_can_set_the_lab_pass_score(): void
    {
        $this->actingAs($this->instructor)->post('/classes', ['name' => 'New Class', 'lab_pass_score' => 65])->assertRedirect();
        $this->assertSame(65, SchoolClass::where('name', 'New Class')->sole()->lab_pass_score);

        $this->actingAs($this->instructor)->put("/classes/{$this->class->id}", ['name' => 'Java 101', 'lab_pass_score' => 55])->assertRedirect();
        $this->assertSame(55, $this->class->fresh()->lab_pass_score);
    }

    public function test_a_new_class_defaults_to_the_standard_lab_pass_score(): void
    {
        $this->actingAs($this->instructor)->post('/classes', ['name' => 'Default Class'])->assertRedirect();

        $this->assertSame(SchoolClass::DEFAULT_LAB_PASS_SCORE, SchoolClass::where('name', 'Default Class')->sole()->lab_pass_score);
    }

    public function test_lab_pass_score_must_be_a_percentage(): void
    {
        $this->actingAs($this->instructor)->post('/classes', ['name' => 'Bad Class', 'lab_pass_score' => 101])->assertSessionHasErrors('lab_pass_score');
    }
}
