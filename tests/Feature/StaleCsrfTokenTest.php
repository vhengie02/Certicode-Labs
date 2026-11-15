<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Signing in or out swaps the CSRF token, and a session ends after SESSION_LIFETIME, so a form
 * left open in another tab or on a shared laptop carries an old token. Posting it must still be
 * refused, but send the browser back to a fresh form instead of a dead-end "419 Page Expired".
 */
class StaleCsrfTokenTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Laravel skips the CSRF check while running tests; these tests are about that check.
        $this->app->bind(ValidateCsrfToken::class, fn ($app) => new class($app, $app['encrypter']) extends ValidateCsrfToken
        {
            protected function runningUnitTests()
            {
                return false;
            }
        });
    }

    private function student(): User
    {
        return User::create([
            'name' => 'Stale Tab',
            'email' => 'stale@example.com',
            'password' => bcrypt('secret-pass'),
            'role' => 'student',
        ]);
    }

    public function test_a_current_token_still_signs_in(): void
    {
        $this->student();

        $this->withSession(['_token' => 'current-token'])
            ->post('/login', ['_token' => 'current-token', 'email' => 'stale@example.com', 'password' => 'secret-pass'])
            ->assertRedirect('/dashboard');

        $this->assertAuthenticated();
    }

    public function test_signing_in_with_an_old_token_returns_to_a_fresh_login_form(): void
    {
        $this->student();

        $response = $this->withSession(['_token' => 'current-token'])
            ->withHeader('Referer', url('/login'))
            ->post('/login', ['_token' => 'old-token', 'email' => 'stale@example.com', 'password' => 'secret-pass']);

        $response->assertRedirect(url('/login'));
        $response->assertSessionHas('error');
        $response->assertSessionHasInput('email', 'stale@example.com');
        $this->assertArrayNotHasKey('password', session('_old_input', []));
        $this->assertArrayNotHasKey('_token', session('_old_input', []));
        $this->assertGuest();
    }

    public function test_an_old_token_from_another_site_is_not_sent_back_there(): void
    {
        $this->withSession(['_token' => 'current-token'])
            ->withHeader('Referer', 'https://evil.example/phish')
            ->post('/login', ['_token' => 'old-token', 'email' => 'x@example.com', 'password' => 'x'])
            ->assertRedirect(url('/'));
    }

    public function test_signing_out_after_the_session_ended_lands_signed_out(): void
    {
        // A fresh session: the one the page was rendered in is gone, so there is no user.
        $this->withSession(['_token' => 'current-token'])
            ->withHeader('Referer', url('/dashboard'))
            ->post('/logout', ['_token' => 'old-token'])
            ->assertRedirect('/')
            ->assertSessionHas('success');

        $this->assertGuest();
    }

    public function test_signing_out_with_an_old_token_while_signed_in_does_not_sign_out(): void
    {
        $user = $this->student();

        $this->actingAs($user)
            ->withSession(['_token' => 'current-token'])
            ->withHeader('Referer', url('/dashboard'))
            ->post('/logout', ['_token' => 'old-token'])
            ->assertRedirect(url('/dashboard'))
            ->assertSessionHas('error');

        $this->assertAuthenticatedAs($user);
    }

    public function test_json_requests_with_an_old_token_still_get_419(): void
    {
        $this->withSession(['_token' => 'current-token'])
            ->postJson('/login', ['_token' => 'old-token', 'email' => 'x@example.com', 'password' => 'x'])
            ->assertStatus(419);
    }
}
