<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InstructorApprovalTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::create(['name' => 'Root Admin', 'email' => 'admin@example.com', 'password' => bcrypt('password'), 'role' => 'admin']);
    }

    private function registerAs(string $role): User
    {
        $this->post('/register', [
            'name' => 'Marta Silva',
            'email' => 'marta@example.com',
            'password' => 'Str0ng!Pass',
            'password_confirmation' => 'Str0ng!Pass',
            'role' => $role,
        ])->assertRedirect('/dashboard');

        return User::where('email', 'marta@example.com')->firstOrFail();
    }

    public function test_signing_up_as_instructor_creates_a_pending_student()
    {
        $admin = $this->admin();
        $user = $this->registerAs('instructor');

        $this->assertSame('student', $user->role);
        $this->assertNotNull($user->instructor_requested_at);
        $this->assertTrue($user->hasPendingInstructorRequest());
        $this->assertSame(1, $admin->notifications()->count(), 'admins are notified of the request');
    }

    public function test_signing_up_as_student_creates_no_request()
    {
        $user = $this->registerAs('student');

        $this->assertSame('student', $user->role);
        $this->assertNull($user->instructor_requested_at);
    }

    public function test_pending_instructor_cannot_use_instructor_pages()
    {
        $user = $this->registerAs('instructor');

        $this->actingAs($user)->get('/classes/create')->assertForbidden();
    }

    public function test_admin_can_approve_a_request()
    {
        $admin = $this->admin();
        $user = $this->registerAs('instructor');
        auth()->logout();

        $this->actingAs($admin)->get('/admin/instructor-requests')->assertOk()->assertSee('marta@example.com');
        $this->actingAs($admin)->post("/admin/instructor-requests/{$user->id}/approve")->assertRedirect();

        $user->refresh();
        $this->assertSame('instructor', $user->role);
        $this->assertNull($user->instructor_requested_at);
        $this->assertSame(1, $user->notifications()->count(), 'the user is told about the decision');
    }

    public function test_admin_can_decline_a_request()
    {
        $admin = $this->admin();
        $user = $this->registerAs('instructor');

        $this->actingAs($admin)->post("/admin/instructor-requests/{$user->id}/decline")->assertRedirect();

        $user->refresh();
        $this->assertSame('student', $user->role);
        $this->assertNull($user->instructor_requested_at);
    }

    public function test_only_admins_can_review_requests()
    {
        $user = $this->registerAs('instructor');
        $instructor = User::create(['name' => 'Prof', 'email' => 'prof@example.com', 'password' => bcrypt('password'), 'role' => 'instructor']);

        $this->actingAs($instructor)->get('/admin/instructor-requests')->assertForbidden();
        $this->actingAs($user)->post("/admin/instructor-requests/{$user->id}/approve")->assertForbidden();
        $this->assertSame('student', $user->fresh()->role);
    }

    public function test_make_admin_command_promotes_an_existing_user()
    {
        User::create(['name' => 'Owner', 'email' => 'owner@example.com', 'password' => bcrypt('password'), 'role' => 'student']);

        $this->artisan('certicode:make-admin', ['email' => 'owner@example.com'])->assertSuccessful();
        $this->assertSame('admin', User::where('email', 'owner@example.com')->value('role'));

        $this->artisan('certicode:make-admin', ['email' => 'missing@example.com'])->assertFailed();
    }
}
