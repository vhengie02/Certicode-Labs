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

    protected function setUp(): void
    {
        parent::setUp();
        // The dashboard caches per user id in the file store, which outlives each test's database
        \Illuminate\Support\Facades\Cache::store('file')->flush();
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

    public function test_instructor_only_sees_flags_from_their_own_classes()
    {
        $mine = $this->user('mine@example.com', 'instructor');
        $other = $this->user('other@example.com', 'instructor');
        $this->flaggedSessionIn($mine, 'CLASS-MINE', 'own_class_flag');
        $this->flaggedSessionIn($other, 'CLASS-OTHER', 'other_class_flag');

        $this->actingAs($mine)->get('/dashboard')
            ->assertOk()
            ->assertSee('Anomaly Telemetry Monitor')
            ->assertSee('own_class_flag')
            ->assertDontSee('other_class_flag');
    }

    public function test_admin_sees_requests_instead_of_telemetry()
    {
        $instructor = $this->user('prof@example.com', 'instructor');
        $this->flaggedSessionIn($instructor, 'CLASS-A', 'some_flag');
        $admin = $this->user('admin@example.com', 'admin');

        $this->actingAs($admin)->get('/dashboard')
            ->assertOk()
            ->assertSee('Instructor requests')
            ->assertDontSee('Anomaly Telemetry Monitor')
            ->assertDontSee('some_flag');
    }

    public function test_activity_graph_is_gone()
    {
        $this->actingAs($this->user('s@example.com', 'student'))->get('/dashboard')
            ->assertOk()
            ->assertDontSee('Collaboration Activity Graph');
    }
}
