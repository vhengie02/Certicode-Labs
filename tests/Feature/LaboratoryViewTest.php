<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\SchoolClass;
use App\Models\Module;
use App\Models\Laboratory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LaboratoryViewTest extends TestCase
{
    use RefreshDatabase;

    public function test_laboratory_view_count_increments_for_student()
    {
        $instructor = User::create([
            'name' => 'Instructor Doe',
            'email' => 'instructor@example.com',
            'password' => bcrypt('password'),
            'role' => 'instructor',
        ]);

        $student = User::create([
            'name' => 'Student Jane',
            'email' => 'student@example.com',
            'password' => bcrypt('password'),
            'role' => 'student',
        ]);

        $schoolClass = SchoolClass::create([
            'name' => 'IT Security Course',
            'code' => 'CLASS-ITSEC1',
            'instructor_id' => $instructor->id,
        ]);

        $schoolClass->students()->attach($student->id, ['status' => 'enrolled']);

        $module = Module::create([
            'class_id' => $schoolClass->id,
            'title' => 'Buffer Overflow Lab',
            'content' => 'Syllabus content',
        ]);

        $laboratory = Laboratory::create([
            'title' => 'Memory Exploitation Challenge',
            'description' => 'Verify registers memory manipulation.',
            'time_limit' => 60,
            'module_id' => $module->id,
            'tasks_definition' => [],
            'views_count' => 0,
        ]);

        $this->assertEquals(0, $laboratory->views_count);

        // Access the laboratory show page as the student
        $response = $this->actingAs($student)->get(route('laboratories.show', $laboratory->id));
        $response->assertStatus(200);

        // Check if the laboratory views count has been incremented in the database
        $this->assertEquals(1, $laboratory->fresh()->views_count);

        // Access again - it shouldn't increment because it's a unique view per student
        $response2 = $this->actingAs($student)->get(route('laboratories.show', $laboratory->id));
        $response2->assertStatus(200);
        $this->assertEquals(1, $laboratory->fresh()->views_count);
    }

    public function test_laboratory_view_count_increments_on_start_session()
    {
        $student = User::create([
            'name' => 'Student Jane',
            'email' => 'student@example.com',
            'password' => bcrypt('password'),
            'role' => 'student',
        ]);

        $laboratory = Laboratory::create([
            'title' => 'Memory Exploitation Challenge',
            'description' => 'Verify registers memory manipulation.',
            'time_limit' => 60,
            'tasks_definition' => [],
            'views_count' => 0,
        ]);

        $this->assertEquals(0, $laboratory->views_count);

        // Start session directly
        $response = $this->actingAs($student)->post(route('laboratories.start', $laboratory->id));
        $response->assertRedirect();

        $this->assertEquals(1, $laboratory->fresh()->views_count);
    }

    public function test_laboratory_view_count_increments_on_show_workspace()
    {
        $student = User::create([
            'name' => 'Student Jane',
            'email' => 'student@example.com',
            'password' => bcrypt('password'),
            'role' => 'student',
        ]);

        $laboratory = Laboratory::create([
            'title' => 'Memory Exploitation Challenge',
            'description' => 'Verify registers memory manipulation.',
            'time_limit' => 60,
            'tasks_definition' => [],
            'views_count' => 0,
        ]);

        $session = \App\Models\LabSession::create([
            'lab_id' => $laboratory->id,
            'user_id' => $student->id,
            'status' => 'in_progress',
            'started_at' => now(),
        ]);

        $this->assertEquals(0, $laboratory->views_count);

        // Show workspace directly
        $response = $this->actingAs($student)->get(route('sessions.show', $session->id));
        $response->assertStatus(200);

        $this->assertEquals(1, $laboratory->fresh()->views_count);
    }
}
