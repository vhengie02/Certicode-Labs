<?php

namespace Tests\Feature;

use App\Models\LabSession;
use App\Models\Laboratory;
use App\Models\Module;
use App\Models\SchoolClass;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Smoke test for the redesigned class, module, lab and settings pages (and the edit pages
 * that share their forms): each renders for the people allowed to see it.
 */
class RedesignedPagesTest extends TestCase
{
    use RefreshDatabase;

    private User $instructor;
    private User $student;
    private SchoolClass $class;
    private Module $module;
    private Laboratory $lab;

    protected function setUp(): void
    {
        parent::setUp();
        $this->instructor = User::create(['name' => 'Maria Santos', 'first_name' => 'Maria', 'last_name' => 'Santos', 'username' => 'maria', 'email' => 'maria@example.com', 'password' => bcrypt('x'), 'role' => 'instructor']);
        $this->student = User::create(['name' => 'Amara Okafor', 'first_name' => 'Amara', 'last_name' => 'Okafor', 'username' => 'amara', 'email' => 'amara@example.com', 'password' => bcrypt('x'), 'role' => 'student']);
        $this->class = SchoolClass::create(['name' => 'Java 101', 'code' => 'JAVA-101', 'instructor_id' => $this->instructor->id, 'passing_threshold' => 60]);
        $this->class->students()->attach($this->student->id, ['status' => 'enrolled']);
        $this->module = Module::create(['class_id' => $this->class->id, 'title' => 'Loops', 'content' => '<p>Lesson</p>']);
        Module::create(['class_id' => $this->class->id, 'parent_id' => $this->module->id, 'title' => 'While loops', 'content' => 'x']);
        $this->lab = Laboratory::create([
            'module_id' => $this->module->id, 'title' => 'FizzBuzz', 'description' => 'Print FizzBuzz', 'time_limit' => 30,
            'tasks_definition' => [['id' => 1, 'task' => 'Use a for loop', 'command' => 'for (']],
            'starter_files' => [['name' => 'Main.java', 'content' => 'class Main {}', 'is_primary' => true, 'is_readonly' => false]],
        ]);
    }

    public function test_instructor_pages_render()
    {
        $c = $this->class->id;
        $pages = [
            '/classes/create' => 'New class',
            "/classes/{$c}" => 'Syllabus',
            "/classes/{$c}/edit" => 'Class settings',
            "/classes/{$c}/modules/create" => 'New module',
            "/classes/{$c}/modules/{$this->module->id}/edit" => 'Edit module',
            "/classes/{$c}/laboratories/create" => 'New lab',
            "/laboratories/{$this->lab->id}" => 'Open monitoring',
            "/laboratories/{$this->lab->id}/edit" => 'Edit lab',
            '/settings' => 'Connected accounts',
        ];
        foreach ($pages as $uri => $text) {
            $this->actingAs($this->instructor)->get($uri)->assertOk()->assertSee($text);
        }
    }

    public function test_class_page_shows_staff_tools_only_to_the_instructor()
    {
        $this->actingAs($this->instructor)->get("/classes/{$this->class->id}")
            ->assertSee('JAVA-101')
            ->assertSee('Invite a student')
            ->assertSee('While loops');

        $this->actingAs($this->student)->get("/classes/{$this->class->id}")
            ->assertOk()
            ->assertSee('Your progress')
            ->assertSee('FizzBuzz')
            ->assertDontSee('JAVA-101')
            ->assertDontSee('Invite a student');
    }

    public function test_student_lab_page_offers_start_and_hides_checks()
    {
        $this->actingAs($this->student)->get("/laboratories/{$this->lab->id}")
            ->assertOk()
            ->assertSee('Start lab')
            ->assertSee('Use a for loop')
            ->assertDontSee('Check:');

        LabSession::create(['lab_id' => $this->lab->id, 'user_id' => $this->student->id, 'status' => 'in_progress', 'started_at' => now()]);
        $this->actingAs($this->student)->get("/laboratories/{$this->lab->id}")->assertSee('Continue in VS Code');
    }

    public function test_lab_form_keeps_typed_tasks_after_a_validation_error()
    {
        $this->actingAs($this->instructor)
            ->from("/classes/{$this->class->id}/laboratories/create")
            ->post('/laboratories', ['title' => '', 'module_id' => $this->module->id, 'tasks' => [['task' => 'Keep me', 'command' => 'regex:x+']]])
            ->assertSessionHasErrors('title');

        $this->actingAs($this->instructor)->get("/classes/{$this->class->id}/laboratories/create")
            ->assertSee('Keep me')
            ->assertSee('regex:x+');
    }
}
