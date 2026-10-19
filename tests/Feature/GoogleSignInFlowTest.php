<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Mail\Events\MessageSent;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * The "Continue with Google" email-code flow: every address proves ownership with an emailed
 * code, existing accounts are signed in without a password, and the resend cooldown only
 * starts once a code has actually been sent.
 */
class GoogleSignInFlowTest extends TestCase
{
    use RefreshDatabase;

    private int $mailsSent = 0;

    protected function setUp(): void
    {
        parent::setUp();
        Event::listen(MessageSent::class, fn () => $this->mailsSent++);
    }

    private function sendCode(string $gmail)
    {
        return $this->from('/auth/google')->post('/auth/google/email', ['gmail' => $gmail]);
    }

    public function test_existing_account_signs_in_with_code_not_password()
    {
        $user = User::create(['name' => 'Vhengie', 'email' => 'vhengie@gmail.com', 'password' => bcrypt('secret-pass'), 'role' => 'student']);

        $this->sendCode('vhengie@gmail.com')->assertRedirect('/auth/google/verify');
        $this->assertSame(1, $this->mailsSent);

        $this->get('/auth/google/verify')->assertOk()->assertDontSee('name="role"', false)->assertDontSee('name="password"', false);

        $this->post('/auth/google/callback', ['code' => session('google_auth_code')])->assertRedirect('/dashboard');
        $this->assertAuthenticatedAs($user);

        $user->refresh();
        $this->assertSame('vhengie@gmail.com', $user->gmail);
        $this->assertNotNull($user->gmail_verified_at);
        $this->assertTrue(\Hash::check('secret-pass', $user->password), 'Signing in with Google must not change the password');
    }

    public function test_account_with_no_known_password_signs_in_with_code()
    {
        // e.g. created by real Google OAuth with a random password the user never saw
        $user = User::create(['name' => 'Oauth', 'email' => 'oauth@gmail.com', 'gmail' => 'oauth@gmail.com', 'gmail_verified_at' => now(), 'password' => bcrypt(\Str::random(32)), 'role' => 'student']);

        $this->sendCode('oauth@gmail.com')->assertRedirect('/auth/google/verify');
        $this->post('/auth/google/callback', ['code' => session('google_auth_code')])->assertRedirect('/dashboard');
        $this->assertAuthenticatedAs($user);
    }

    public function test_linked_gmail_signs_into_its_account_and_keeps_account_email()
    {
        $user = User::create(['name' => 'Linked', 'email' => 'linked@school.edu', 'gmail' => 'linked@gmail.com', 'gmail_verified_at' => now(), 'password' => bcrypt('secret-pass'), 'role' => 'student']);

        $this->sendCode('linked@gmail.com')->assertRedirect('/auth/google/verify');
        $this->post('/auth/google/callback', ['code' => session('google_auth_code')])->assertRedirect('/dashboard');
        $this->assertAuthenticatedAs($user);
        $this->assertSame('linked@school.edu', $user->fresh()->email);
    }

    public function test_new_gmail_gets_code_then_role_step_and_account_is_created()
    {
        $this->sendCode('brand.new@gmail.com')->assertRedirect('/auth/google/verify?needs_role=1');
        $this->assertSame(1, $this->mailsSent);

        $this->post('/auth/google/callback', ['code' => session('google_auth_code'), 'role' => 'student', 'password' => 'long-enough-pass'])
            ->assertRedirect('/dashboard');

        $user = User::where('email', 'brand.new@gmail.com')->firstOrFail();
        $this->assertSame('brand.new@gmail.com', $user->gmail);
        $this->assertAuthenticatedAs($user);
    }

    public function test_new_account_takes_over_a_stale_unverified_gmail_claim()
    {
        $other = User::create(['name' => 'Other', 'email' => 'other@school.edu', 'gmail' => 'claimed@gmail.com', 'password' => bcrypt('x'), 'role' => 'student']);

        $this->sendCode('claimed@gmail.com')->assertRedirect('/auth/google/verify?needs_role=1');
        $this->post('/auth/google/callback', ['code' => session('google_auth_code'), 'role' => 'student', 'password' => 'long-enough-pass'])
            ->assertRedirect('/dashboard');

        $this->assertNull($other->fresh()->gmail);
        $this->assertSame('claimed@gmail.com', User::where('email', 'claimed@gmail.com')->value('gmail'));
    }

    public function test_github_asserted_email_cannot_sign_into_an_existing_google_account()
    {
        User::create(['name' => 'Owner', 'email' => 'owner@school.edu', 'gmail' => 'owner@gmail.com', 'gmail_verified_at' => now(), 'password' => bcrypt('x'), 'role' => 'student']);

        $this->withSession(['google_auth_gmail' => 'owner@gmail.com', 'google_auth_oauth_email' => 'owner@gmail.com', 'github_auth_username' => 'intruder'])
            ->post('/auth/google/callback')
            ->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_register_with_existing_email_points_to_sign_in()
    {
        User::create(['name' => 'Vhengie', 'email' => 'vhengie@gmail.com', 'password' => bcrypt('secret-pass'), 'role' => 'student']);

        $this->from('/register')->post('/register', [
            'name' => 'Vhengie', 'email' => 'vhengie@gmail.com', 'password' => 'Long-enough-1', 'password_confirmation' => 'Long-enough-1', 'role' => 'student',
        ])->assertRedirect('/register')->assertSessionHasErrors(['email' => 'An account with this email already exists. Sign in instead, or use Continue with Google to get a sign-in code by email.']);
    }

    public function test_invalid_email_shows_inline_error_and_sends_nothing()
    {
        foreach (['vhengie@gmail', 'not-an-email', 'two@@gmail.com', ''] as $bad) {
            $this->sendCode($bad)->assertRedirect('/auth/google')->assertSessionHasErrors('gmail');
        }

        $this->assertSame(0, $this->mailsSent);
        $this->assertNull(session('google_auth_code_sent_at'));

        $this->sendCode('vhengie@gmail')->assertRedirect('/auth/google');
        $this->followRedirects($this->sendCode('vhengie@gmail'))
            ->assertSee('Enter a valid Google email address, like you@gmail.com.')
            ->assertDontSee('Please wait')
            ->assertDontSee('already sent a code');
    }

    public function test_invalid_email_during_cooldown_shows_validation_error_and_keeps_cooldown()
    {
        $this->sendCode('first@gmail.com')->assertRedirect('/auth/google/verify?needs_role=1');
        $sentAt = session('google_auth_code_sent_at');
        $code = session('google_auth_code');

        $this->travel(10)->seconds();
        $this->sendCode('first@gmail')->assertRedirect('/auth/google')->assertSessionHasErrors('gmail')->assertSessionDoesntHaveErrors('code');

        $this->assertEquals($sentAt, session('google_auth_code_sent_at'), 'Invalid input must not reset the cooldown');
        $this->assertSame($code, session('google_auth_code'), 'Invalid input must not discard the code already sent');
        $this->assertSame('first@gmail.com', session('google_auth_gmail'));
        $this->assertSame(1, $this->mailsSent);
    }

    public function test_resending_to_same_address_within_cooldown_is_held_back()
    {
        $this->sendCode('first@gmail.com');
        $code = session('google_auth_code');

        $this->travel(13)->seconds();
        $this->sendCode('first@gmail.com')
            ->assertRedirect('/auth/google/verify?needs_role=1')
            ->assertSessionHasErrors(['code' => 'We already sent a code to first@gmail.com. Enter it below, or wait 47 seconds to request a new one.']);

        $this->assertSame(1, $this->mailsSent);
        $this->assertSame($code, session('google_auth_code'), 'The code already sent stays valid');

        $this->travel(48)->seconds();
        $this->sendCode('first@gmail.com')->assertRedirect('/auth/google/verify?needs_role=1')->assertSessionHasNoErrors();
        $this->assertSame(2, $this->mailsSent);
    }

    public function test_different_valid_address_is_not_blocked_by_cooldown()
    {
        $this->sendCode('typo@gmial.com');
        $this->sendCode('fixed@gmail.com')->assertRedirect('/auth/google/verify?needs_role=1')->assertSessionHasNoErrors();

        $this->assertSame(2, $this->mailsSent);
        $this->assertSame('fixed@gmail.com', session('google_auth_gmail'));
    }

    public function test_no_cooldown_when_mail_could_not_be_sent()
    {
        $this->app['env'] = 'production';
        $this->withoutMiddleware(ValidateCsrfToken::class);
        Mail::shouldReceive('send')->andThrow(new \Exception('SMTP down'));

        $this->sendCode('first@gmail.com')->assertRedirect('/auth/google')->assertSessionHasErrors('gmail');
        $this->assertNull(session('google_auth_code_sent_at'));
        $this->assertNull(session('google_auth_code'));
    }
}
