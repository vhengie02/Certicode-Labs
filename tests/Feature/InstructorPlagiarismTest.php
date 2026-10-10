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
        $response = $this->actingAs($this->instructor)->followingRedirects()->get(route('instructor.monitoring.show', $this->lab->id));
        $response->assertStatus(200);
        $response->assertSee('Similar code');
        $response->assertSee('In-Session Cohort Plagiarism Detector');
    }

    public function test_monitoring_view_removes_live_telemetry_badge_and_has_telemetry_button(): void
    {
        $response = $this->actingAs($this->instructor)->followingRedirects()->get(route('instructor.monitoring.show', $this->lab->id));
        $response->assertStatus(200);
        $response->assertDontSee('Live Telemetry');
        // The lab view sits inside the class monitoring page, with an "All labs" tab back to the overview
        $response->assertSee('All labs');
        $response->assertSee('href="' . route('classes.telemetry', $this->lab->module->schoolClass->id) . '"', false);
    }

    public function test_monitoring_view_for_solo_lab_hides_group_controls(): void
    {
        $this->lab->update(['is_group_lab' => false]);

        $response = $this->actingAs($this->instructor)->followingRedirects()->get(route('instructor.monitoring.show', $this->lab->id));
        $response->assertStatus(200);
        $response->assertDontSee('By team');
        $response->assertSee('Active Student Workspaces');
        $response->assertSee('Filter by student name...');
    }

    public function test_monitoring_view_for_group_lab_shows_group_controls(): void
    {
        $this->lab->update(['is_group_lab' => true]);

        $response = $this->actingAs($this->instructor)->followingRedirects()->get(route('instructor.monitoring.show', $this->lab->id));
        $response->assertStatus(200);
        $response->assertSee('By team');
        $response->assertSee('Active Student / Team Workspaces');
        $response->assertSee('Filter by student or team...');
    }

    public function test_monitoring_view_suppresses_websocket_when_broadcast_connection_is_log(): void
    {
        config(['broadcasting.default' => 'log']);

        $response = $this->actingAs($this->instructor)->followingRedirects()->get(route('instructor.monitoring.show', $this->lab->id));
        $response->assertStatus(200);
        $response->assertSee('this.initPolling()');
        $response->assertDontSee('this.initWebSocket()');
    }

    public function test_old_lab_monitoring_url_opens_the_class_page_with_that_lab_selected(): void
    {
        $classId = $this->lab->module->schoolClass->id;

        $this->actingAs($this->instructor)->get(route('instructor.monitoring.show', $this->lab->id))
            ->assertRedirect(route('classes.telemetry', ['class_id' => $classId, 'lab' => $this->lab->id]));
    }

    public function test_class_monitoring_page_lists_labs_and_switches_between_them(): void
    {
        $classId = $this->lab->module->schoolClass->id;

        $overview = $this->actingAs($this->instructor)->get(route('classes.telemetry', $classId));
        $overview->assertOk()
            ->assertSee('Integrity flags')
            ->assertSee('Binary Trees')
            ->assertSee('href="' . route('classes.telemetry', ['class_id' => $classId, 'lab' => $this->lab->id]) . '"', false)
            ->assertSee('role="tooltip"', false);

        $this->actingAs($this->instructor)->get(route('classes.telemetry', ['class_id' => $classId, 'lab' => $this->lab->id]))
            ->assertOk()
            ->assertSee('Connected now')
            ->assertSee('In-Session Cohort Plagiarism Detector');
    }

    public function test_class_monitoring_rejects_a_lab_from_another_class(): void
    {
        $otherClass = SchoolClass::create(['name' => 'Other', 'code' => 'CLASS-OTHER1', 'instructor_id' => $this->instructor->id]);
        $classId = $this->lab->module->schoolClass->id;

        $this->actingAs($this->instructor)->get(route('classes.telemetry', ['class_id' => $otherClass->id, 'lab' => $this->lab->id]))
            ->assertNotFound();
        $this->assertNotEquals($otherClass->id, $classId);
    }

    public function test_student_names_cannot_inject_script_into_the_roster_filter(): void
    {
        $this->student1->update(['name' => "x'); alert(1); ('"]);
        LabSession::create(['lab_id' => $this->lab->id, 'user_id' => $this->student1->id, 'status' => 'in_progress', 'started_at' => now()]);

        $html = $this->actingAs($this->instructor)
            ->get(route('classes.telemetry', ['class_id' => $this->lab->module->schoolClass->id, 'lab' => $this->lab->id]))
            ->assertOk()
            ->getContent();

        // The quote must stay inside an encoded JS string, never close the argument early
        $this->assertStringNotContainsString("matchesSearch('x&#039;); alert(1)", $html);
        $this->assertStringNotContainsString("matchesSearch('x'); alert(1)", $html);
        $this->assertStringContainsString('matchesSearch(', $html);
    }
}
