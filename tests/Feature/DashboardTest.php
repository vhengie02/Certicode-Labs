<?php

namespace Tests\Feature;

use App\Models\Anomaly;
use App\Models\LabSession;
use App\Models\Laboratory;
use App\Models\Module;
use App\Models\SchoolClass;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    private function user(string $email, string $role): User
    {
        return User::create(['name' => ucfirst($role), 'email' => $email, 'password' => bcrypt('password'), 'role' => $role]);
    }

    /** Creates a class owned by $instructor containing one session flagged with $type. */
    private function flaggedSessionIn(User $instructor, string $code, string $type): void
    {
        $class = SchoolClass::create(['name' => "Class {$code}", 'code' => $code, 'instructor_id' => $instructor->id]);
        $module = Module::create(['class_id' => $class->id, 'title' => 'Module', 'content' => 'Content']);
        $lab = Laboratory::create(['title' => 'Lab', 'description' => 'Lab', 'time_limit' => 60, 'module_id' => $module->id, 'tasks_definition' => []]);
        $student = $this->user("student-{$code}@example.com", 'student');
        $session = LabSession::create(['lab_id' => $lab->id, 'user_id' => $student->id, 'status' => 'in_progress', 'started_at' => now()]);
        Anomaly::create(['lab_session_id' => $session->id, 'type' => $type, 'severity' => 'high', 'description' => 'Flag', 'resolved' => false]);
    }

    private function sections(User $user)
    {
        return $this->actingAs($user)->get('/dashboard/sections', ['X-Requested-With' => 'XMLHttpRequest']);
    }

    public function test_dashboard_page_renders_skeleton_without_loading_data()
    {
        $instructor = $this->user('prof@example.com', 'instructor');
        $this->flaggedSessionIn($instructor, 'CLASS-A', 'some_flag');

        $this->actingAs($instructor)->get('/dashboard')
            ->assertOk()
            ->assertSee('data-lazy-src="' . route('dashboard.sections') . '"', false)
            ->assertSee('skeleton-pulse', false)
            ->assertDontSee('some flag');
    }

    public function test_greeting_skips_titles_in_the_name()
    {
        $user = User::create(['name' => 'Dr. Maria Santos', 'email' => 'dr@example.com', 'password' => bcrypt('x'), 'role' => 'instructor']);

        $this->actingAs($user)->get('/dashboard')->assertOk()->assertSee('Maria.')->assertDontSee('Dr..');
    }

    public function test_sections_opened_directly_go_back_to_the_dashboard()
    {
        $this->actingAs($this->user('s@example.com', 'student'))->get('/dashboard/sections')
            ->assertRedirect(route('dashboard'));
    }

    public function test_sections_require_sign_in()
    {
        // The loader sees the redirect to /login and reloads the page, which then shows the login screen
        $this->get('/dashboard/sections', ['X-Requested-With' => 'XMLHttpRequest'])->assertRedirect(route('login'));
    }

    public function test_instructor_only_sees_flags_from_their_own_classes()
    {
        $mine = $this->user('mine@example.com', 'instructor');
        $other = $this->user('other@example.com', 'instructor');
        $this->flaggedSessionIn($mine, 'CLASS-MINE', 'own_class_flag');
        $this->flaggedSessionIn($other, 'CLASS-OTHER', 'other_class_flag');

        $this->sections($mine)
            ->assertOk()
            ->assertSee('Recent integrity flags')
            ->assertSee('own class flag')
            ->assertDontSee('other class flag')
            ->assertSee('Class CLASS-MINE')
            ->assertDontSee('Class CLASS-OTHER');
    }

    public function test_admin_sees_requests_instead_of_flags()
    {
        $instructor = $this->user('prof@example.com', 'instructor');
        $this->flaggedSessionIn($instructor, 'CLASS-A', 'some_flag');
        $admin = $this->user('admin@example.com', 'admin');
        User::create(['name' => 'Lin Wei', 'email' => 'lin@example.com', 'password' => bcrypt('x'), 'role' => 'student'])
            ->forceFill(['instructor_requested_at' => now()])->save();

        $this->sections($admin)
            ->assertOk()
            ->assertSee('Instructor requests')
            ->assertSee('lin@example.com')
            ->assertDontSee('Recent integrity flags')
            ->assertDontSee('some flag');
    }

    public function test_student_sees_their_labs_in_progress()
    {
        $instructor = $this->user('prof@example.com', 'instructor');
        $this->flaggedSessionIn($instructor, 'CLASS-A', 'some_flag');
        $student = User::where('email', 'student-CLASS-A@example.com')->firstOrFail();

        $this->sections($student)
            ->assertOk()
            ->assertSee('Continue where you left off')
            ->assertSee('Class CLASS-A')
            ->assertDontSee('some flag');
    }

    public function test_main_menu_shows_admin_pages_only_to_admins()
    {
        $adminLinks = [route('students.index'), route('admin.instructor-requests.index')];

        $adminPage = $this->actingAs($this->user('admin@example.com', 'admin'))->get('/dashboard')->assertOk();
        foreach ($adminLinks as $link) {
            $adminPage->assertSee('href="' . $link . '"', false);
        }

        $instructorPage = $this->actingAs($this->user('prof@example.com', 'instructor'))->get('/dashboard')->assertOk();
        $instructorPage->assertSee('href="' . route('classes.index') . '"', false);
        foreach ($adminLinks as $link) {
            $instructorPage->assertDontSee('href="' . $link . '"', false);
        }
    }

    public function test_activity_graph_is_gone()
    {
        $this->actingAs($this->user('s@example.com', 'student'))->get('/dashboard')
            ->assertOk()
            ->assertDontSee('Collaboration Activity Graph');
    }
}
