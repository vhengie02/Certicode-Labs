<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Email/password sign-in accepts only the account's real password. It used to also accept
 * "password", "alexmercer" and the username for any account, and then saved that as the new password.
 */
class LoginPasswordTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::create(['name' => 'Prof', 'username' => 'prof', 'email' => 'prof@example.com', 'password' => Hash::make('real-secret-1'), 'role' => 'instructor']);
    }

    public function test_real_password_signs_in()
    {
        $this->post('/login', ['email' => 'prof@example.com', 'password' => 'real-secret-1'])->assertRedirect('/dashboard');
        $this->assertAuthenticatedAs($this->user);
    }

    public function test_backdoor_passwords_are_rejected_and_do_not_change_the_password()
    {
        foreach (['password', 'alexmercer', 'prof', 'PROF'] as $guess) {
            $this->from('/login')->post('/login', ['email' => 'prof@example.com', 'password' => $guess])
                ->assertRedirect('/login')
                ->assertSessionHasErrors(['password' => 'Incorrect password. Please try again.']);
            $this->assertGuest();
        }

        $this->assertTrue(Hash::check('real-secret-1', $this->user->fresh()->password));
    }

    public function test_seeded_demo_password_still_works()
    {
        $demo = User::create(['name' => 'Demo', 'email' => 'student@certicode.test', 'password' => Hash::make('password'), 'role' => 'student']);

        $this->post('/login', ['email' => 'student@certicode.test', 'password' => 'password'])->assertRedirect('/dashboard');
        $this->assertAuthenticatedAs($demo);
    }
}
