<?php

namespace Tests\Feature;

use App\Models\Laboratory;
use App\Models\LabSession;
use App\Models\SchoolClass;
use App\Models\Module;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InstructorPlagiarismTest extends TestCase
{
    use RefreshDatabase;

    protected User $instructor;
    protected User $student1;
    protected User $student2;
    protected Laboratory $lab;

    protected function setUp(): void
    {
        parent::setUp();

        $this->instructor = User::create([
            'name' => 'Professor Alan',
            'email' => 'alan@university.test',
            'password' => bcrypt('password'),
            'role' => 'instructor',
        ]);

        $this->student1 = User::create([
            'name' => 'John Doe',
            'email' => 'john@university.test',
            'password' => bcrypt('password'),
            'role' => 'student',
        ]);

        $this->student2 = User::create([
            'name' => 'Jane Doe',
            'email' => 'jane@university.test',
            'password' => bcrypt('password'),
            'role' => 'student',
        ]);

        $class = SchoolClass::create([
            'name' => 'Data Structures 101',
            'code' => 'DS101',
            'instructor_id' => $this->instructor->id,
        ]);

        $module = Module::create([
            'class_id' => $class->id,
            'title' => 'Recursion',
            'order_index' => 1,
        ]);

        $this->lab = Laboratory::create([
            'module_id' => $module->id,
            'title' => 'Binary Trees',
            'description' => 'Implement Tree Search',
            'language' => 'php',
        ]);
    }

    public function test_instructor_can_access_plagiarism_endpoint(): void
    {
        // Student 1 submission
        LabSession::create([
            'lab_id' => $this->lab->id,
            'user_id' => $this->student1->id,
            'status' => 'completed',
            'submitted_code' => 'function findMax($arr) { return max($arr); }',
        ]);

        // Student 2 identical submission
        LabSession::create([
            'lab_id' => $this->lab->id,
            'user_id' => $this->student2->id,
            'status' => 'completed',
            'submitted_code' => 'function findMax($items) { return max($items); }',
        ]);

        $response = $this->actingAs($this->instructor)->getJson(route('instructor.monitoring.plagiarism', $this->lab->id));

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'status',
            'analysis' => [
                'total_students_with_code',
                'flagged_pairs_count',
                'flagged_pairs',
                'matrix',
            ]
        ]);

        $data = $response->json('analysis');
        $this->assertEquals(2, $data['total_students_with_code']);
        $this->assertGreaterThanOrEqual(1, $data['flagged_pairs_count']);
    }

    public function test_student_cannot_access_plagiarism_endpoint(): void
    {
        $response = $this->actingAs($this->student1)->getJson(route('instructor.monitoring.plagiarism', $this->lab->id));
        $response->assertStatus(403);
    }

    public function test_monitoring_view_renders_plagiarism_section(): void
    {
        $response = $this->actingAs($this->instructor)->get(route('instructor.monitoring.show', $this->lab->id));
        $response->assertStatus(200);
        $response->assertSee('Cohort Plagiarism');
        $response->assertSee('In-Session Cohort Plagiarism Detector');
    }

    public function test_monitoring_view_removes_live_telemetry_badge_and_has_telemetry_button(): void
    {
        $response = $this->actingAs($this->instructor)->get(route('instructor.monitoring.show', $this->lab->id));
        $response->assertStatus(200);
        $response->assertDontSee('Live Telemetry');
        $response->assertSee('Telemetry Monitoring');
        $response->assertSee(route('classes.telemetry', $this->lab->module->schoolClass->id));
    }

    public function test_monitoring_view_for_solo_lab_hides_group_controls(): void
    {
        $this->lab->update(['is_group_lab' => false]);

        $response = $this->actingAs($this->instructor)->get(route('instructor.monitoring.show', $this->lab->id));
        $response->assertStatus(200);
        $response->assertDontSee('By Group / Team');
        $response->assertSee('Active Student Workspaces');
        $response->assertSee('Filter by student name...');
    }

    public function test_monitoring_view_for_group_lab_shows_group_controls(): void
    {
        $this->lab->update(['is_group_lab' => true]);

        $response = $this->actingAs($this->instructor)->get(route('instructor.monitoring.show', $this->lab->id));
        $response->assertStatus(200);
        $response->assertSee('By Group / Team');
        $response->assertSee('Active Student / Team Workspaces');
        $response->assertSee('Filter by student or team...');
    }

    public function test_monitoring_view_suppresses_websocket_when_broadcast_connection_is_log(): void
    {
        config(['broadcasting.default' => 'log']);

        $response = $this->actingAs($this->instructor)->get(route('instructor.monitoring.show', $this->lab->id));
        $response->assertStatus(200);
        $response->assertSee('this.initPolling()');
        $response->assertDontSee('this.initWebSocket()');
    }
}
