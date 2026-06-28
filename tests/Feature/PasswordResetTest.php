<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::create([
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'gmail' => 'john@example.com',
            'gmail_verified_at' => now(),
            'password' => Hash::make('old-password'),
            'role' => 'student',
        ]);
    }

    public function test_forgot_password_form_loads(): void
    {
        $response = $this->get('/forgot-password');
        $response->assertStatus(200);
        $response->assertSee('Forgot Password');
    }

    public function test_reset_link_sent_successfully_for_valid_email(): void
    {
        $response = $this->post('/forgot-password', [
            'email' => 'john@example.com',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('status');

        $this->assertDatabaseHas('password_reset_tokens', [
            'email' => 'john@example.com',
        ]);
    }

    public function test_reset_link_fails_for_invalid_email(): void
    {
        $response = $this->post('/forgot-password', [
            'email' => 'nonexistent@example.com',
        ]);

        $response->assertSessionHasErrors(['email']);
    }

    public function test_password_reset_page_loads(): void
    {
        $response = $this->get('/reset-password/sample-token?email=john@example.com');
        $response->assertStatus(200);
        $response->assertSee('Reset Password');
    }

    public function test_password_can_be_reset_successfully_with_valid_token(): void
    {
        $token = 'reset-token-123';
        DB::table('password_reset_tokens')->insert([
            'email' => 'john@example.com',
            'token' => Hash::make($token),
            'created_at' => now(),
        ]);

        $response = $this->post('/reset-password', [
            'token' => $token,
            'email' => 'john@example.com',
            'password' => 'new-secure-password',
            'password_confirmation' => 'new-secure-password',
        ]);

        $response->assertRedirect('/login');
        $response->assertSessionHas('status', 'Your password has been reset successfully. Please log in with your new password.');

        $this->user->refresh();
        $this->assertTrue(Hash::check('new-secure-password', $this->user->password));

        // The token should be cleaned up
        $this->assertDatabaseMissing('password_reset_tokens', [
            'email' => 'john@example.com',
        ]);
    }

    public function test_password_cannot_be_reset_with_invalid_token(): void
    {
        DB::table('password_reset_tokens')->insert([
            'email' => 'john@example.com',
            'token' => Hash::make('real-token'),
            'created_at' => now(),
        ]);

        $response = $this->post('/reset-password', [
            'token' => 'invalid-token',
            'email' => 'john@example.com',
            'password' => 'new-secure-password',
            'password_confirmation' => 'new-secure-password',
        ]);

        $response->assertSessionHasErrors(['email']);
        $this->assertFalse(Hash::check('new-secure-password', $this->user->fresh()->password));
    }

    public function test_password_cannot_be_reset_with_expired_token(): void
    {
        $token = 'expired-token';
        DB::table('password_reset_tokens')->insert([
            'email' => 'john@example.com',
            'token' => Hash::make($token),
            'created_at' => now()->subMinutes(61), // Exceeds 60 min
        ]);

        $response = $this->post('/reset-password', [
            'token' => $token,
            'email' => 'john@example.com',
            'password' => 'new-secure-password',
            'password_confirmation' => 'new-secure-password',
        ]);

        $response->assertSessionHasErrors(['email']);
        $this->assertFalse(Hash::check('new-secure-password', $this->user->fresh()->password));
    }

    public function test_google_forgot_password_sends_link(): void
    {
        $response = $this->withSession([
            'google_auth_gmail' => 'john@example.com',
        ])->post('/auth/google/forgot-password');

        $response->assertRedirect('/login');
        $response->assertSessionHas('status');

        $this->assertDatabaseHas('password_reset_tokens', [
            'email' => 'john@example.com',
        ]);
    }

    public function test_login_specifies_unregistered_email_error(): void
    {
        $response = $this->post('/login', [
            'email' => 'notregistered@example.com',
            'password' => 'some-password',
        ]);

        $response->assertSessionHasErrors(['email' => 'This email address is not registered.']);
    }

    public function test_login_specifies_wrong_password_error(): void
    {
        $response = $this->post('/login', [
            'email' => 'john@example.com',
            'password' => 'wrong-password',
        ]);

        $response->assertSessionHasErrors(['password' => 'Incorrect password. Please try again.']);
    }
}
