<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * When email delivery fails in production, reset links and verification codes must never be
 * shown on screen: that would let anyone reset a password or verify an inbox they don't own.
 */
class AuthSecretExposureTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::shouldReceive('send')->andThrow(new \RuntimeException('SMTP unavailable'));
    }

    private function asProduction(): void
    {
        $this->app['env'] = 'production';
        // CSRF is only skipped automatically in the testing environment
        $this->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class);
    }

    public function test_failed_password_reset_email_does_not_reveal_the_link_in_production()
    {
        User::create(['name' => 'Ana Reyes', 'email' => 'ana@example.com', 'password' => bcrypt('password'), 'role' => 'student']);
        $this->asProduction();

        $response = $this->from('/forgot-password')->post('/forgot-password', ['email' => 'ana@example.com']);

        $response->assertSessionHasErrors('email');
        $this->assertStringNotContainsString('reset-password', (string) session('status'));
    }

    public function test_failed_password_reset_email_still_shows_the_link_locally()
    {
        User::create(['name' => 'Ana Reyes', 'email' => 'ana@example.com', 'password' => bcrypt('password'), 'role' => 'student']);

        $this->from('/forgot-password')->post('/forgot-password', ['email' => 'ana@example.com']);

        $this->assertStringContainsString('reset-password', (string) session('status'));
    }

    public function test_failed_gmail_code_email_does_not_reveal_the_code_in_production()
    {
        $user = User::create(['name' => 'Ana Reyes', 'email' => 'ana@example.com', 'password' => bcrypt('password'), 'role' => 'student']);
        $this->asProduction();

        $response = $this->actingAs($user)->post('/settings/gmail/connect', ['gmail' => 'ana.reyes@gmail.com']);

        $response->assertSessionHasErrors('gmail');
        $this->assertStringNotContainsString((string) $user->fresh()->gmail_verification_code, (string) session('success'));
    }
}
