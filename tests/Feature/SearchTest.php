<?php

namespace Tests\Feature;

use App\Models\SchoolClass;
use App\Models\Module;
use App\Models\Laboratory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SearchTest extends TestCase
{
    use RefreshDatabase;

    protected User $student;
    protected User $otherStudent;
    protected User $instructor;
    protected SchoolClass $classA;
    protected SchoolClass $classB;
    protected Module $moduleA;
    protected Laboratory $labA;

    protected function setUp(): void
    {
        parent::setUp();

        $this->student = User::create([
            'name' => 'Jane Student',
            'email' => 'student@example.com',
            'password' => bcrypt('password'),
            'role' => 'student',
        ]);

        $this->otherStudent = User::create([
            'name' => 'Bob Other',
            'email' => 'bob@example.com',
            'password' => bcrypt('password'),
            'role' => 'student',
        ]);

        $this->instructor = User::create([
            'name' => 'Dr. Instructor',
            'email' => 'instructor@example.com',
            'password' => bcrypt('password'),
            'role' => 'instructor',
        ]);

        // Class A: owned by Instructor, Student is enrolled
        $this->classA = SchoolClass::create([
            'name' => 'Introduction to Laravel',
            'code' => 'CLASS-LARA1',
            'instructor_id' => $this->instructor->id,
            'description' => 'A basic course in PHP MVC structure.',
        ]);
        $this->classA->students()->attach($this->student->id, ['status' => 'enrolled']);

        // Module A inside Class A
        $this->moduleA = Module::create([
            'class_id' => $this->classA->id,
            'title' => 'Blade Views',
            'description' => 'Rendering templates with layout files.',
            'content' => 'Sample content about layout files.',
            'order_index' => 1,
        ]);

        // Lab A inside Module A
        $this->labA = Laboratory::create([
            'title' => 'HTML Elements Challenge',
            'description' => 'Solve basic tag structures.',
            'time_limit' => 30,
            'module_id' => $this->moduleA->id,
            'tasks_definition' => [],
        ]);

        // Class B: Student is NOT enrolled
        $this->classB = SchoolClass::create([
            'name' => 'Python Basics',
            'code' => 'CLASS-PY101',
            'instructor_id' => $this->instructor->id,
            'description' => 'Python course details.',
        ]);
    }

    /**
     * Test guest cannot access search.
     */
    public function test_guest_cannot_access_search(): void
    {
        $response = $this->get('/search?q=Laravel');
        $response->assertRedirect('/login');
    }

    /**
     * Test empty query or query less than 2 chars returns empty list.
     */
    public function test_short_query_returns_empty_results(): void
    {
        $response = $this->actingAs($this->student)->get('/search?q=L');
        $response->assertStatus(200)
            ->assertJson([
                'classes' => [],
                'modules' => [],
                'laboratories' => [],
            ]);
    }

    /**
     * Test student search filters class they are enrolled in.
     */
    public function test_student_finds_enrolled_class_but_not_unenrolled_class(): void
    {
        // Query "Laravel" which matches Class A (enrolled)
        $response1 = $this->actingAs($this->student)->get('/search?q=Laravel');
        $response1->assertStatus(200);
        $this->assertCount(1, $response1->json('classes'));
        $this->assertEquals('Introduction to Laravel (CLASS-LARA1)', $response1->json('classes.0.label'));

        // Query "Python" which matches Class B (not enrolled)
        $response2 = $this->actingAs($this->student)->get('/search?q=Python');
        $response2->assertStatus(200);
        $this->assertCount(0, $response2->json('classes'));
    }

    /**
     * Test student search finds module and laboratories they have access to.
     */
    public function test_student_finds_accessible_modules_and_laboratories(): void
    {
        $response = $this->actingAs($this->student)->get('/search?q=Blade');
        $response->assertStatus(200);
        $this->assertCount(1, $response->json('modules'));
        $this->assertEquals('Blade Views', $response->json('modules.0.label'));

        $responseLab = $this->actingAs($this->student)->get('/search?q=HTML');
        $responseLab->assertStatus(200);
        $this->assertCount(1, $responseLab->json('laboratories'));
        $this->assertEquals('HTML Elements Challenge', $responseLab->json('laboratories.0.label'));
    }

    /**
     * Test instructor finds classes they instruct.
     */
    public function test_instructor_finds_instructed_classes(): void
    {
        $response = $this->actingAs($this->instructor)->get('/search?q=Python');
        $response->assertStatus(200);
        $this->assertCount(1, $response->json('classes'));
        $this->assertEquals('Python Basics (CLASS-PY101)', $response->json('classes.0.label'));
    }
}
