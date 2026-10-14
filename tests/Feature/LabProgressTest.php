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

/**
 * Progress counts distinct completed labs, so retaking a lab can't push a student over the
 * pass mark. Certificates are issued through every path on that same number.
 */
class LabProgressTest extends TestCase
{
    use RefreshDatabase;

    private User $instructor;
    private User $student;
    private SchoolClass $class;
    private Module $module;
    private Laboratory $labA;
    private Laboratory $labB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->instructor = User::create(['name' => 'Ina Instructor', 'email' => 'ina@example.com', 'password' => bcrypt('x'), 'role' => 'instructor']);
        $this->student = User::create(['name' => 'Rae Retaker', 'email' => 'rae@example.com', 'password' => bcrypt('x'), 'role' => 'student']);

        $this->class = SchoolClass::create([
            'name' => 'Data Structures',
            'code' => 'DS-101',
            'instructor_id' => $this->instructor->id,
            'passing_threshold' => 75,
        ]);
        $this->class->students()->attach($this->student->id, ['status' => 'enrolled']);

        $this->module = Module::create(['class_id' => $this->class->id, 'title' => 'Lists', 'content' => 'Linked lists']);
        $this->labA = Laboratory::create(['title' => 'Lab A', 'description' => 'A', 'time_limit' => 30, 'module_id' => $this->module->id]);
        $this->labB = Laboratory::create(['title' => 'Lab B', 'description' => 'B', 'time_limit' => 30, 'module_id' => $this->module->id]);
    }

    private function complete(Laboratory $lab): void
    {
        LabSession::create([
            'lab_id' => $lab->id,
            'user_id' => $this->student->id,
            'status' => 'completed',
            'started_at' => now()->subMinutes(10),
            'ended_at' => now(),
            'performance_score' => 90.0,
        ]);
    }

    private function retakeLabATwice(): void
    {
        $this->complete($this->labA);
        $this->complete($this->labA);
    }

    public function test_retaking_a_lab_counts_it_once_in_class_and_module_progress()
    {
        $this->retakeLabATwice();

        $class = $this->class->fresh();
        $module = $this->module->fresh();
        $completedIds = LabSession::completedLabIdsFor($this->student->id);

        foreach ([
            $class->getStudentProgress($this->student),
            $class->getStudentProgress($this->student, $completedIds),
            $module->getStudentProgress($this->student),
            $module->getStudentProgress($this->student, $completedIds),
        ] as $progress) {
            $this->assertSame(1, $progress['completed']);
            $this->assertSame(2, $progress['total']);
            $this->assertEquals(50, $progress['percent']);
        }
    }

    public function test_class_page_shows_the_same_count_the_certificate_gate_uses()
    {
        $this->retakeLabATwice();

        $page = $this->actingAs($this->student)->get("/classes/{$this->class->id}")->assertOk();
        $gate = Certificate::issueFor($this->class->fresh(), $this->student)['progress'];

        $this->assertSame(1, $gate['completed']);
        $page->assertSee("{$gate['completed']} of {$gate['total']} labs done");
    }

    public function test_reopening_a_completed_session_drops_the_cached_progress()
    {
        $this->complete($this->labA);
        $this->complete($this->labB);
        $this->assertEquals(100, $this->class->fresh()->getStudentProgress($this->student)['percent']); // now cached

        LabSession::where('lab_id', $this->labB->id)->first()->update(['status' => 'in_progress']);

        $this->actingAs($this->student)
            ->post("/classes/{$this->class->id}/claim-certificate")
            ->assertSessionHas('error');
        $this->assertSame(0, Certificate::count());
    }

    public function test_deleting_a_completed_session_drops_the_cached_progress()
    {
        $this->complete($this->labA);
        $this->complete($this->labB);
        $this->assertEquals(100, $this->class->fresh()->getStudentProgress($this->student)['percent']);

        LabSession::where('lab_id', $this->labB->id)->first()->delete();

        $this->assertEquals(50, $this->class->fresh()->getStudentProgress($this->student)['percent']);
    }

    public function test_ending_a_class_only_certifies_enrolled_students()
    {
        $this->complete($this->labA);
        $this->complete($this->labB);
        $this->class->students()->updateExistingPivot($this->student->id, ['status' => 'invited']);

        $this->actingAs($this->instructor)->post("/classes/{$this->class->id}/end")->assertRedirect();

        $this->assertSame(0, Certificate::count());
    }

    public function test_retakes_do_not_earn_a_certificate_by_claiming()
    {
        $this->retakeLabATwice();

        $this->actingAs($this->student)
            ->post("/classes/{$this->class->id}/claim-certificate")
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertSame(0, Certificate::count());
    }

    public function test_retakes_do_not_earn_a_certificate_when_the_instructor_ends_the_class()
    {
        $this->retakeLabATwice();

        $this->actingAs($this->instructor)->post("/classes/{$this->class->id}/end")->assertRedirect();

        $this->assertSame('completed', $this->class->fresh()->status);
        $this->assertSame(0, Certificate::count());
    }

    public function test_retakes_do_not_earn_a_certificate_when_the_class_auto_concludes()
    {
        $this->retakeLabATwice();
        $this->class->update(['scheduled_end_date' => now()->subDay()]);

        $this->artisan('certicode:auto-conclude-expired-classes')->assertSuccessful();

        $this->assertSame('completed', $this->class->fresh()->status);
        $this->assertSame(0, Certificate::count());
    }

    public function test_finishing_every_lab_still_earns_a_certificate_by_claiming()
    {
        $this->complete($this->labA);
        $this->complete($this->labB);

        $this->actingAs($this->student)->post("/classes/{$this->class->id}/claim-certificate")->assertRedirect();
        // Claiming again returns the same certificate rather than a second one
        $this->actingAs($this->student)->post("/classes/{$this->class->id}/claim-certificate")->assertRedirect();

        $this->assertSame(1, Certificate::where('user_id', $this->student->id)->where('class_id', $this->class->id)->count());
    }

    public function test_finishing_every_lab_still_earns_a_certificate_when_the_instructor_ends_the_class()
    {
        $this->complete($this->labA);
        $this->complete($this->labB);

        $this->actingAs($this->instructor)
            ->post("/classes/{$this->class->id}/end")
            ->assertSessionHas('success', fn ($message) => str_contains($message, '1 qualifying student'));

        $certificate = Certificate::where('user_id', $this->student->id)->firstOrFail();
        $this->assertMatchesRegularExpression('/^CERT-[A-Z0-9]{12}$/', $certificate->verification_code);
        $this->assertSame(1, $this->student->notifications()->where('data', 'like', '%Course Completed%')->count());
    }

    public function test_finishing_every_lab_still_earns_a_certificate_when_the_class_auto_concludes()
    {
        $this->complete($this->labA);
        $this->complete($this->labB);
        $this->class->update(['scheduled_end_date' => now()->subDay()]);

        $this->artisan('certicode:auto-conclude-expired-classes')->assertSuccessful();

        $this->assertSame(1, Certificate::where('user_id', $this->student->id)->count());
    }
}
