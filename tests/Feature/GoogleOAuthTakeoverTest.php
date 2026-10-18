<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Socialite\Facades\Socialite;
use Mockery;
use Tests\TestCase;

/**
 * Regression tests for the Google sign-in account takeover: an OAuth proof for one address
 * (or an emailed code for one address) must never sign in to a different address.
 */
class GoogleOAuthTakeoverTest extends TestCase
{
    use RefreshDatabase;

    private function fakeGoogleOAuth(string $email): void
    {
        config()->set('services.google.client_id', 'google-id');
        config()->set('services.google.client_secret', 'google-secret');

        $socialUser = Mockery::mock(\Laravel\Socialite\Two\User::class);
        $socialUser->shouldReceive('getEmail')->andReturn($email);
        $socialUser->shouldReceive('getName')->andReturn('OAuth User');
        $socialUser->shouldReceive('getNickname')->andReturn(null);

        $provider = Mockery::mock(\Laravel\Socialite\Two\GoogleProvider::class);
        $provider->shouldReceive('user')->andReturn($socialUser);

        Socialite::shouldReceive('driver')->with('google')->andReturn($provider);
    }

    public function test_oauth_proof_cannot_be_moved_onto_another_account_through_the_resend_cooldown(): void
    {
        User::create(['name' => 'Victim Admin', 'email' => 'victim@gmail.com', 'password' => bcrypt('secret-pass'), 'role' => 'admin']);
        $this->fakeGoogleOAuth('attacker@gmail.com');

        // 1. Ask for a code for the victim's address (the email goes to the victim, not the attacker)
        $this->from('/auth/google')->post('/auth/google/email', ['gmail' => 'victim@gmail.com']);

        // 2. Finish real Google OAuth with the attacker's own, unregistered Google account
        $this->get('/auth/google/callback')->assertRedirect('/auth/google/verify?needs_role=1');

        // 3. Re-submit the victim's address inside the 60-second resend cooldown
        $this->from('/auth/google')->post('/auth/google/email', ['gmail' => 'victim@gmail.com']);
        $this->assertNull(session('google_auth_oauth_email'), 'Typing an address must drop the OAuth proof');

        // 4. Neither the old magic value, nor no code, nor a sign-up attempt gets in
        $this->post('/auth/google/callback', ['code' => 'OAUTH_VERIFIED']);
        $this->assertGuest();

        $this->post('/auth/google/callback')->assertSessionHasErrors('code');
        $this->assertGuest();

        $this->post('/auth/google/callback', ['role' => 'student', 'password' => 'long-enough-pass']);
        $this->assertGuest();
        $this->assertSame(1, User::count());
    }

    public function test_an_emailed_code_only_works_for_the_address_it_was_sent_to(): void
    {
        User::create(['name' => 'Victim', 'email' => 'victim@gmail.com', 'password' => bcrypt('secret-pass'), 'role' => 'instructor']);

        // The attacker receives a real code at their own address...
        $this->from('/auth/google')->post('/auth/google/email', ['gmail' => 'attacker@gmail.com']);
        $code = session('google_auth_code');
        $this->assertNotEmpty($code);

        // ...then the session is pointed at the victim's address while that code is still in it
        $this->withSession(['google_auth_gmail' => 'victim@gmail.com'])
            ->post('/auth/google/callback', ['code' => $code])
            ->assertRedirect(route('auth.google'));

        $this->assertGuest();
    }

    public function test_an_abandoned_github_signup_is_not_attached_to_the_next_code_signup(): void
    {
        config()->set('services.github.client_id', 'github-id');
        config()->set('services.github.client_secret', 'github-secret');
        $githubUser = Mockery::mock(\Laravel\Socialite\Two\User::class);
        $githubUser->shouldReceive('getEmail')->andReturn('attacker@example.com');
        $githubUser->shouldReceive('getName')->andReturn('Attacker Name');
        $githubUser->shouldReceive('getNickname')->andReturn('attacker-gh');
        $provider = Mockery::mock(\Laravel\Socialite\Two\GithubProvider::class);
        $provider->shouldReceive('user')->andReturn($githubUser);
        Socialite::shouldReceive('driver')->with('github')->andReturn($provider);

        // 1. On a shared browser, someone starts a GitHub sign-up and walks away at the role page
        $this->get('/auth/github/callback')->assertRedirect('/auth/google/verify?needs_role=1');

        // 2. The next person signs up with their own address and the code emailed to it
        $this->from('/auth/google')->post('/auth/google/email', ['gmail' => 'student@gmail.com']);
        $this->post('/auth/google/callback', ['code' => session('google_auth_code'), 'role' => 'student', 'password' => 'long-enough-pass'])
            ->assertRedirect('/dashboard');

        // 3. The new account carries neither the GitHub identity nor the stranger's name
        $student = User::where('email', 'student@gmail.com')->firstOrFail();
        $this->assertNull($student->github_username);
        $this->assertNotSame('Attacker Name', $student->name);
    }

    public function test_oauth_signup_still_finishes_without_a_code_for_the_proven_address(): void
    {
        $this->fakeGoogleOAuth('newcomer@gmail.com');

        $this->get('/auth/google/callback')->assertRedirect('/auth/google/verify?needs_role=1');
        $this->get('/auth/google/verify?needs_role=1')->assertOk()->assertDontSee('name="code"', false);

        $this->post('/auth/google/callback', ['role' => 'student', 'password' => 'long-enough-pass'])
            ->assertRedirect('/dashboard');

        $this->assertAuthenticated();
        $this->assertDatabaseHas('users', ['email' => 'newcomer@gmail.com', 'role' => 'student']);
    }
}
