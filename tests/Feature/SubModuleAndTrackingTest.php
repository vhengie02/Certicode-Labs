<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\SchoolClass;
use App\Models\Module;
use App\Models\Laboratory;
use App\Models\LabSession;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SubModuleAndTrackingTest extends TestCase
{
    use RefreshDatabase;

    protected User $student;
    protected User $instructor;
    protected SchoolClass $class;

    protected function setUp(): void
    {
        parent::setUp();

        $this->instructor = User::create([
            'name' => 'Instructor Joe',
            'email' => 'instructor@joe.com',
            'password' => bcrypt('password'),
            'role' => 'instructor',
        ]);

        $this->student = User::create([
            'name' => 'Student Sam',
            'email' => 'student@sam.com',
            'password' => bcrypt('password'),
            'role' => 'student',
        ]);

        $this->class = SchoolClass::create([
            'name' => 'Java Programming',
            'code' => 'CLASS-JAVA1',
            'instructor_id' => $this->instructor->id,
        ]);

        // Enroll student
        $this->class->students()->attach($this->student->id, ['status' => 'enrolled']);
    }

    /**
     * Test only one view per student is counted.
     */
    public function test_unique_module_views_per_student(): void
    {
        $module = Module::create([
            'class_id' => $this->class->id,
            'title' => 'Introduction to Java',
            'content' => 'Syllabus content',
            'views_count' => 0,
        ]);

        $this->assertEquals(0, $module->views_count);

        // View 1
        $this->actingAs($this->student)
            ->get("/classes/{$this->class->id}/modules/{$module->id}");

        $this->assertEquals(1, $module->fresh()->views_count);

        // View 2 (from same student)
        $this->actingAs($this->student)
            ->get("/classes/{$this->class->id}/modules/{$module->id}");

        // Views count should still be 1
        $this->assertEquals(1, $module->fresh()->views_count);
    }

    /**
     * Test students cannot see views and submissions.
     */
    public function test_students_cannot_see_views_and_submissions(): void
    {
        $module = Module::create([
            'class_id' => $this->class->id,
            'title' => 'Java OOP Concepts',
            'content' => 'Objects and Classes',
        ]);

        // Student views course home page
        $response = $this->actingAs($this->student)
            ->get("/classes/{$this->class->id}");

        $response->assertStatus(200);
        $response->assertDontSee('views');
        $response->assertDontSee('completed'); // 'completed' is under submissions for labs
    }

    /**
     * Test instructors CAN see views and submissions.
     */
    public function test_instructors_can_see_views_and_submissions(): void
    {
        $module = Module::create([
            'class_id' => $this->class->id,
            'title' => 'Java OOP Concepts',
            'content' => 'Objects and Classes',
        ]);

        $response = $this->actingAs($this->instructor)
            ->get("/classes/{$this->class->id}");

        $response->assertStatus(200);
        $response->assertSee('Views');
        $response->assertSee('Submissions');
    }

    /**
     * Test student progress is calculated correctly.
     */
    public function test_student_progress_calculation(): void
    {
        $module = Module::create([
            'class_id' => $this->class->id,
            'title' => 'Exception Handling',
            'content' => 'Try catch blocks',
        ]);

        $lab1 = Laboratory::create([
            'title' => 'Try Catch Lab',
            'description' => 'Fix errors',
            'time_limit' => 30,
            'module_id' => $module->id,
        ]);

        $lab2 = Laboratory::create([
            'title' => 'Custom Exception Lab',
            'description' => 'Create exception class',
            'time_limit' => 45,
            'module_id' => $module->id,
        ]);

        // No progress initially
        $progress = $module->getStudentProgress($this->student);
        $this->assertNotNull($progress);
        $this->assertEquals(0, $progress['completed']);
        $this->assertEquals(2, $progress['total']);
        $this->assertEquals(0, $progress['percent']);

        // Complete 1 lab session
        LabSession::create([
            'lab_id' => $lab1->id,
            'user_id' => $this->student->id,
            'status' => 'completed',
            'started_at' => now(),
            'ended_at' => now(),
            'performance_score' => 100.0,
        ]);

        $progress = $module->getStudentProgress($this->student);
        $this->assertEquals(1, $progress['completed']);
        $this->assertEquals(2, $progress['total']);
        $this->assertEquals(50, $progress['percent']);
    }

    /**
     * Test sub-modules can be created by instructor.
     */
    public function test_instructor_can_create_sub_modules(): void
    {
        $parentModule = Module::create([
            'class_id' => $this->class->id,
            'title' => 'Module 1: Java Basics',
            'content' => 'Basics description',
        ]);

        $response = $this->actingAs($this->instructor)
            ->post("/classes/{$this->class->id}/modules", [
                'title' => '1.1 Introduction to Java',
                'description' => 'Java history and setup',
                'content' => 'Setting up JDK',
                'order_index' => 1,
                'parent_id' => $parentModule->id,
            ]);

        $response->assertRedirect();

        $subModule = Module::where('parent_id', $parentModule->id)->first();
        $this->assertNotNull($subModule);
        $this->assertEquals('1.1 Introduction to Java', $subModule->title);
        $this->assertEquals($parentModule->id, $subModule->parent_id);
    }
}
