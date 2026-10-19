<?php

namespace Tests\Feature;

use App\Models\SchoolClass;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Roles change only in Admin > Users, where the safeguards are. The profile form must not
 * be a way around them.
 */
class ProfileRoleGuardTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $instructor;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::create(['name' => 'Ada Admin', 'email' => 'ada@example.com', 'password' => bcrypt('password'), 'role' => 'admin']);
        $this->instructor = User::create(['name' => 'Ian Prof', 'email' => 'ian@example.com', 'password' => bcrypt('password'), 'role' => 'instructor']);
        SchoolClass::create(['name' => 'CS 101', 'code' => 'CS101', 'instructor_id' => $this->instructor->id]);
    }

    private function saveProfile(User $user, string $role)
    {
        return $this->actingAs($this->admin)->put(route('profiles.update', $user->id), [
            'first_name' => 'Some',
            'last_name' => 'Name',
            'username' => 'user'.$user->id,
            'email' => $user->email,
            'role' => $role,
        ]);
    }

    public function test_the_only_admin_cannot_demote_themself_from_the_profile_form(): void
    {
        $this->saveProfile($this->admin, 'student')->assertSessionHasNoErrors();

        $this->assertSame('admin', $this->admin->fresh()->role);
        $this->assertSame('Some Name', $this->admin->fresh()->name);
    }

    public function test_a_teaching_instructor_cannot_be_demoted_from_the_profile_form(): void
    {
        $this->saveProfile($this->instructor, 'student')->assertSessionHasNoErrors();

        $this->assertSame('instructor', $this->instructor->fresh()->role);
    }

    public function test_the_profile_form_has_no_role_field(): void
    {
        $this->actingAs($this->admin)->get(route('profiles.edit', $this->instructor->id))
            ->assertOk()
            ->assertDontSee('name="role"', false)
            ->assertSee(route('admin.users.index'), false);
    }
}
