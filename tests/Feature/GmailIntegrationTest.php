<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\SchoolClass;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use App\Notifications\ClassActivityNotification;
use Tests\TestCase;

class GmailIntegrationTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::create([
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'password' => bcrypt('password'),
            'role' => 'student',
        ]);
    }

    /**
     * Test user can view the settings page.
     */
    public function test_user_can_view_settings_page(): void
    {
        $response = $this->actingAs($this->user)->get('/settings');
        $response->assertStatus(200);
        $response->assertSee('Gmail Integration');
        $response->assertSee('Notification Preferences');
    }

    /**
     * Test notification preferences update.
     */
    public function test_user_can_update_notification_preferences(): void
    {
        $response = $this->actingAs($this->user)
            ->post('/settings/notifications', [
                'notify_class' => '1',
                // notify_module is unchecked, so it won't be sent
                'notify_lab' => '1',
                'notify_certificate' => '1',
                'notify_email_channel' => '1',
            ]);

        $response->assertRedirect('/settings');
        $response->assertSessionHas('success');

        $this->user->refresh();
        $this->assertTrue($this->user->notify_class);
        $this->assertFalse($this->user->notify_module);
        $this->assertTrue($this->user->notify_lab);
        $this->assertTrue($this->user->notify_certificate);
        $this->assertTrue($this->user->notify_email_channel);
    }

    /**
     * Test connecting Gmail triggers verification code generation.
     */
    public function test_connecting_gmail_generates_code(): void
    {
        $response = $this->actingAs($this->user)
            ->post('/settings/gmail/connect', [
                'gmail' => 'john.doe@gmail.com',
            ]);

        $response->assertRedirect('/settings');
        $response->assertSessionHas('success');

        $this->user->refresh();
        $this->assertEquals('john.doe@gmail.com', $this->user->gmail);
        $this->assertNotNull($this->user->gmail_verification_code);
        $this->assertNull($this->user->gmail_verified_at);
        $this->assertNotEmpty(session('gmail_code_debug'));
    }

    /**
     * Test verification code validation works.
     */
    public function test_submitting_correct_code_verifies_gmail(): void
    {
        // First request connection
        $this->actingAs($this->user)
            ->post('/settings/gmail/connect', [
                'gmail' => 'john.doe@gmail.com',
            ]);

        $this->user->refresh();
        $correctCode = $this->user->gmail_verification_code;

        // Verify code
        $response = $this->actingAs($this->user)
            ->post('/settings/gmail/verify', [
                'code' => $correctCode,
            ]);

        $response->assertRedirect('/settings');
        $response->assertSessionHas('success');

        $this->user->refresh();
        $this->assertNull($this->user->gmail_verification_code);
        $this->assertNotNull($this->user->gmail_verified_at);
    }

    /**
     * Test verification code validation rejects invalid code.
     */
    public function test_submitting_incorrect_code_fails(): void
    {
        // First request connection
        $this->actingAs($this->user)
            ->post('/settings/gmail/connect', [
                'gmail' => 'john.doe@gmail.com',
            ]);

        // Verify with invalid code
        $response = $this->actingAs($this->user)
            ->post('/settings/gmail/verify', [
                'code' => '000000',
            ]);

        $response->assertRedirect('/settings');
        $response->assertSessionHasErrors(['code']);

        $this->user->refresh();
        $this->assertNotNull($this->user->gmail_verification_code);
        $this->assertNull($this->user->gmail_verified_at);
    }

    /**
     * Test Google Mock Login.
     */
    public function test_google_login_loads_mock_page(): void
    {
        $response = $this->get('/auth/google');
        $response->assertStatus(200);
        $response->assertSee('Sign in');
    }

    /**
     * Test successful login via mock Google Callback.
     */
    public function test_google_callback_logs_in_verified_user(): void
    {
        // Setup user with verified gmail connection
        $this->user->update([
            'gmail' => 'verified@gmail.com',
            'gmail_verified_at' => now(),
        ]);

        $response = $this->withSession([
            'google_auth_gmail' => 'verified@gmail.com',
            'google_auth_code' => '123456',
        ])->post('/auth/google/callback', [
            'code' => '123456',
        ]);

        $response->assertRedirect('/dashboard');
        $this->assertAuthenticatedAs($this->user);
    }

    /**
     * Test failed login via mock Google Callback for unverified/unlinked Gmail.
     */
    public function test_google_callback_rejects_unverified_gmail(): void
    {
        // Unverified gmail address
        $this->user->update([
            'email' => 'unverified@gmail.com',
            'gmail' => 'unverified@gmail.com',
            'gmail_verified_at' => null,
        ]);

        $response = $this->withSession([
            'google_auth_gmail' => 'unverified@gmail.com',
            'google_auth_code' => '123456',
        ])->post('/auth/google/callback', [
            'code' => '123456',
        ]);

        $response->assertRedirect('/login');
        $response->assertSessionHasErrors();
        $this->assertGuest();
    }

    /**
     * Test Gmail connected users receive email notifications too.
     */
    public function test_email_notifications_sent_if_connected_and_enabled(): void
    {
        Notification::fake();

        // 1. Unconnected gmail -> database only
        $this->user->notify(new ClassActivityNotification('Title', 'Message', '/url', 'class'));
        Notification::assertSentTo($this->user, ClassActivityNotification::class, function ($notification, $channels) {
            return count($channels) === 1 && $channels[0] === 'database';
        });

        // 2. Verified connected gmail + all alerts enabled -> database and mail
        $this->user->update([
            'gmail' => 'verified@gmail.com',
            'gmail_verified_at' => now(),
            'notify_email_channel' => true,
            'notify_class' => true,
        ]);

        $this->user->notify(new ClassActivityNotification('Title', 'Message', '/url', 'class'));
        Notification::assertSentTo($this->user, ClassActivityNotification::class, function ($notification, $channels) {
            return in_array('database', $channels) && in_array('mail', $channels);
        });

        // 3. Verified connected gmail but class alerts disabled -> database only
        $this->user->update([
            'notify_class' => false,
        ]);

        $this->user->notify(new ClassActivityNotification('Title', 'Message', '/url', 'class'));
        Notification::assertSentTo($this->user, ClassActivityNotification::class, function ($notification, $channels) {
            return in_array('database', $channels) && !in_array('mail', $channels);
        });
    }

    /**
     * Test a user is prompted for role selection when Gmail is unregistered.
     */
    public function test_google_callback_redirects_to_role_selection_for_unregistered_gmail(): void
    {
        $response = $this->post('/auth/google/email', [
            'gmail' => 'newuser@gmail.com',
        ]);

        $response->assertRedirect('/auth/google/verify?needs_role=1');
    }

    /**
     * Test a user is prompted for password when Gmail already exists.
     */
    public function test_google_email_submission_redirects_to_password_if_exists(): void
    {
        $this->user->update([
            'gmail' => 'john@example.com',
            'gmail_verified_at' => now(),
        ]);

        $response = $this->post('/auth/google/email', [
            'gmail' => 'john@example.com',
        ]);

        $response->assertRedirect('/auth/google/password');
    }

    /**
     * Test successful login via mock Google password screen.
     */
    public function test_google_password_login_for_existing_user(): void
    {
        $this->user->update([
            'gmail' => 'john@example.com',
            'gmail_verified_at' => now(),
        ]);

        $response = $this->withSession([
            'google_auth_gmail' => 'john@example.com',
        ])->post('/auth/google/password', [
            'password' => 'password',
        ]);

        $response->assertRedirect('/dashboard');
        $this->assertAuthenticatedAs($this->user);
    }

    /**
     * Test failed login via mock Google password screen.
     */
    public function test_google_password_login_fails_for_incorrect_password(): void
    {
        $this->user->update([
            'gmail' => 'john@example.com',
            'gmail_verified_at' => now(),
        ]);

        $response = $this->withSession([
            'google_auth_gmail' => 'john@example.com',
        ])->post('/auth/google/password', [
            'password' => 'wrong-password',
        ]);

        $response->assertSessionHasErrors(['password']);
        $this->assertGuest();
    }

    /**
     * Test successful registration with role selection.
     */
    public function test_google_callback_registers_new_user_with_role(): void
    {
        $response = $this->withSession([
            'google_auth_gmail' => 'newuser@gmail.com',
            'google_auth_code' => '123456',
        ])->post('/auth/google/callback', [
            'code' => '123456',
            'role' => 'student',
        ]);

        $response->assertRedirect('/dashboard');
        $this->assertDatabaseHas('users', [
            'email' => 'newuser@gmail.com',
            'gmail' => 'newuser@gmail.com',
            'role' => 'student',
        ]);
        
        $user = User::where('email', 'newuser@gmail.com')->first();
        $this->assertNotNull($user->gmail_verified_at);
        $this->assertAuthenticatedAs($user);
    }
}
