<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\GoogleProvider;
use Mockery;
use Tests\TestCase;

/**
 * The sign-in buttons open Google in a small window. Its last page hands the result back to the
 * page that opened it, so the server must know when a sign-in runs in that window.
 */
class GooglePopupSignInTest extends TestCase
{
    use RefreshDatabase;

    private function configureGoogle(): void
    {
        config()->set('services.google.client_id', 'google-id');
        config()->set('services.google.client_secret', 'google-secret');
    }

    private function fakeGoogleUser(string $email): void
    {
        $this->configureGoogle();

        $socialUser = Mockery::mock(\Laravel\Socialite\Two\User::class);
        $socialUser->shouldReceive('getEmail')->andReturn($email);
        $socialUser->shouldReceive('getName')->andReturn('Popup User');
        $socialUser->shouldReceive('getNickname')->andReturn(null);

        $provider = Mockery::mock(GoogleProvider::class);
        $provider->shouldReceive('user')->andReturn($socialUser);

        Socialite::shouldReceive('driver')->with('google')->andReturn($provider);
    }

    private function existingUser(string $email): User
    {
        return User::create([
            'name' => 'Popup User',
            'email' => $email,
            'gmail' => $email,
            'gmail_verified_at' => now(),
            'password' => bcrypt('secret-pass'),
            'role' => 'student',
        ]);
    }

    public function test_popup_redirect_still_goes_to_googles_account_chooser(): void
    {
        $this->configureGoogle();

        $location = $this->get('/auth/google/redirect?popup=1')->assertStatus(302)->headers->get('Location');

        $this->assertStringStartsWith('https://accounts.google.com/', $location);
        $this->assertStringContainsString('prompt=select_account', $location);
        $this->assertTrue(session('oauth_popup'));
    }

    public function test_popup_callback_hands_the_sign_in_back_to_the_opening_page(): void
    {
        $user = $this->existingUser('popup-user@gmail.com');
        $this->fakeGoogleUser('popup-user@gmail.com');

        $response = $this->withSession(['oauth_popup' => true])->get('/auth/google/callback');

        $response->assertOk()->assertViewIs('auth.oauth-popup-done');
        $response->assertViewHas('target', url('/dashboard'));
        $response->assertSee('certicode-oauth', false);
        $this->assertAuthenticatedAs($user);
        $this->assertNull(session('oauth_popup'), 'The flag must not outlive one sign-in');
    }

    public function test_popup_callback_sends_a_new_google_account_to_the_sign_up_step(): void
    {
        $this->fakeGoogleUser('brand-new@gmail.com');

        $response = $this->withSession(['oauth_popup' => true])->get('/auth/google/callback');

        $response->assertOk()->assertViewHas('target', route('auth.google.verify', ['needs_role' => 1]));
        $this->assertGuest();
        $this->assertSame('brand-new@gmail.com', session('google_auth_oauth_email'));
    }

    public function test_popup_callback_keeps_the_message_for_the_opening_page(): void
    {
        $this->existingUser('popup-user@gmail.com');
        $this->fakeGoogleUser('popup-user@gmail.com');

        $this->withSession(['oauth_popup' => true])->get('/auth/google/callback')->assertOk();

        // The opening page makes the next request, and that is where the message belongs
        $this->assertSame('Logged in via Google successfully!', session('success'));
    }

    public function test_full_page_callback_still_redirects(): void
    {
        $this->existingUser('popup-user@gmail.com');
        $this->fakeGoogleUser('popup-user@gmail.com');

        $this->get('/auth/google/callback')->assertRedirect('/dashboard');
    }

    public function test_full_page_redirect_clears_a_flag_left_by_an_abandoned_popup(): void
    {
        $this->configureGoogle();

        $this->withSession(['oauth_popup' => true])->get('/auth/google/redirect')->assertStatus(302);

        $this->assertNull(session('oauth_popup'));
    }

    public function test_sign_in_pages_only_load_the_popup_script_when_google_is_set_up(): void
    {
        $this->get('/login')->assertOk()->assertDontSee("BroadcastChannel('certicode-oauth')", false);
        $this->get('/register')->assertOk()->assertDontSee("BroadcastChannel('certicode-oauth')", false);

        $this->configureGoogle();

        $this->get('/login')->assertOk()
            ->assertSee('data-oauth-popup', false)
            ->assertSee("BroadcastChannel('certicode-oauth')", false);
        $this->get('/register')->assertOk()
            ->assertSee('data-oauth-popup', false)
            ->assertSee("BroadcastChannel('certicode-oauth')", false);
    }
}
