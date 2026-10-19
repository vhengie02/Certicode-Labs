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
 * Regression tests for deleting a class: it used to cascade away issued certificates (whose
 * verify links then failed) and orphan the class's labs and student attempts.
 */
class ClassDeletionGuardTest extends TestCase
{
    use RefreshDatabase;

    private User $instructor;

    private User $student;

    private SchoolClass $class;

    protected function setUp(): void
    {
        parent::setUp();

        $this->instructor = User::create(['name' => 'Prof', 'email' => 'prof@example.com', 'password' => bcrypt('password'), 'role' => 'instructor']);
        $this->student = User::create(['name' => 'Student', 'email' => 'student@example.com', 'password' => bcrypt('password'), 'role' => 'student']);
        $this->class = SchoolClass::create(['name' => 'Java 101', 'code' => 'JAVA-101', 'instructor_id' => $this->instructor->id]);
    }

    public function test_a_class_with_issued_certificates_is_not_deleted(): void
    {
        $certificate = Certificate::create(['user_id' => $this->student->id, 'class_id' => $this->class->id, 'verification_code' => 'CERT-ABC123DEF456', 'issued_at' => now()]);

        $this->actingAs($this->instructor)->delete("/classes/{$this->class->id}")
            ->assertRedirect(route('classes.index'))
            ->assertSessionHas('error');

        $this->assertModelExists($this->class);
        $this->assertModelExists($certificate);
        $this->get('/verify-certificate/CERT-ABC123DEF456')->assertOk()->assertSee('Java 101');
    }

    public function test_a_class_with_labs_is_not_deleted(): void
    {
        $module = Module::create(['class_id' => $this->class->id, 'title' => 'Module', 'content' => 'Content']);
        $lab = Laboratory::create(['module_id' => $module->id, 'title' => 'Loops', 'description' => 'Write a loop.', 'time_limit' => 45, 'is_group_lab' => false, 'tasks_definition' => []]);
        $session = LabSession::create(['lab_id' => $lab->id, 'user_id' => $this->student->id, 'status' => 'completed', 'started_at' => now(), 'performance_score' => 90]);

        $this->actingAs($this->instructor)->delete("/classes/{$this->class->id}")->assertSessionHas('error');

        $this->assertModelExists($this->class);
        $this->assertSame($module->id, $lab->fresh()->module_id);
        $this->assertModelExists($session);
    }

    public function test_an_empty_class_can_still_be_deleted(): void
    {
        Module::create(['class_id' => $this->class->id, 'title' => 'Module', 'content' => 'Content']);

        $this->actingAs($this->instructor)->delete("/classes/{$this->class->id}")
            ->assertRedirect(route('classes.index'))
            ->assertSessionHas('success');

        $this->assertModelMissing($this->class);
    }
}
