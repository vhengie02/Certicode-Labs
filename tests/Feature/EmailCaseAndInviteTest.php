<?php

namespace Tests\Feature;

use App\Models\SchoolClass;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * One address is one account whatever its letter case, and inviting a student never
 * changes an enrolment that already exists.
 */
class EmailCaseAndInviteTest extends TestCase
{
    use RefreshDatabase;

    /** A user saved before emails were lowercased, exactly as they typed it. */
    private function legacyUser(string $email, string $role = 'student'): User
    {
        $id = DB::table('users')->insertGetId([
            'name' => 'Ana Reyes',
            'email' => $email,
            'password' => bcrypt('secret-pass-1'),
            'role' => $role,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return User::findOrFail($id);
    }

    public function test_the_same_address_in_another_case_cannot_register_again(): void
    {
        $this->legacyUser('Ana@Example.com');

        $this->post('/register', [
            'name' => 'Ana Again',
            'email' => 'ana@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'role' => 'student',
        ])->assertSessionHasErrors('email');

        $this->assertSame(1, User::count());
    }

    public function test_new_accounts_store_the_email_lowercased(): void
    {
        $this->post('/register', [
            'name' => 'Ben Cruz',
            'email' => '  Ben.Cruz@Example.COM ',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'role' => 'student',
        ])->assertRedirect('/dashboard');

        $this->assertSame('ben.cruz@example.com', User::firstOrFail()->email);
    }

    public function test_sign_in_finds_the_account_whatever_case_is_typed(): void
    {
        $user = $this->legacyUser('Ana@Example.com');

        $this->post('/login', ['email' => 'ana@example.com', 'password' => 'secret-pass-1'])->assertRedirect('/dashboard');

        $this->assertAuthenticatedAs($user);
    }

    public function test_migration_lowercases_existing_emails_but_leaves_duplicates(): void
    {
        $single = $this->legacyUser('Solo@Example.com');
        $dupeA = $this->legacyUser('Twin@Example.com');
        $dupeB = $this->legacyUser('twin@example.com');

        (require database_path('migrations/2026_10_19_000200_lowercase_user_emails.php'))->up();

        $this->assertSame('solo@example.com', $single->fresh()->email);
        $this->assertSame('Twin@Example.com', $dupeA->fresh()->email);
        $this->assertSame('twin@example.com', $dupeB->fresh()->email);
    }

    public function test_inviting_an_enrolled_student_keeps_them_enrolled(): void
    {
        $instructor = User::create(['name' => 'Prof', 'email' => 'prof@example.com', 'password' => bcrypt('password'), 'role' => 'instructor']);
        $student = User::create(['name' => 'Ana', 'email' => 'ana@example.com', 'password' => bcrypt('password'), 'role' => 'student']);
        $class = SchoolClass::create(['name' => 'CS 101', 'code' => 'CS101', 'instructor_id' => $instructor->id]);
        $class->students()->attach([$student->id => ['status' => 'enrolled']]);

        $this->actingAs($instructor)->post(route('classes.invite', $class->id), ['email' => 'ANA@example.com'])
            ->assertRedirect(route('classes.show', $class->id));

        $this->assertSame('enrolled', $class->students()->where('users.id', $student->id)->first()->pivot->status);
    }

    public function test_invites_reach_new_students_only(): void
    {
        $instructor = User::create(['name' => 'Prof', 'email' => 'prof@example.com', 'password' => bcrypt('password'), 'role' => 'instructor']);
        $otherInstructor = User::create(['name' => 'Other', 'email' => 'other@example.com', 'password' => bcrypt('password'), 'role' => 'instructor']);
        $student = User::create(['name' => 'Ben', 'email' => 'ben@example.com', 'password' => bcrypt('password'), 'role' => 'student']);
        $class = SchoolClass::create(['name' => 'CS 101', 'code' => 'CS101', 'instructor_id' => $instructor->id]);

        $this->actingAs($instructor)->post(route('classes.invite', $class->id), ['email' => 'ben@example.com']);
        $this->actingAs($instructor)->post(route('classes.invite', $class->id), ['email' => 'other@example.com'])
            ->assertSessionHas('error');

        $this->assertSame('invited', $class->students()->where('users.id', $student->id)->first()->pivot->status);
        $this->assertNull($class->students()->where('users.id', $otherInstructor->id)->first());
    }
}
