<?php

namespace Tests\Feature;

use App\Models\User;
use App\Notifications\ClassActivityNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Symfony\Component\Mime\Email;
use Tests\TestCase;

/**
 * Code and reset emails were landing in spam. Two things in the app's control made that worse:
 * the wording named Google and Gmail, which reads as phishing from a sender that is neither, and
 * the emails had no plain-text part. These tests hold both fixes in place for every auth email.
 */
class AuthEmailWordingTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

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

    private function onlySentEmail(): Email
    {
        $sent = app('mailer')->getSymfonyTransport()->messages();
        $this->assertCount(1, $sent, 'Expected exactly one email');

        return $sent->first()->getOriginalMessage();
    }

    /** The subject and the plain-text part hold every word a reader sees. */
    private function assertPlainAndBrandSafe(Email $email): string
    {
        $text = (string) $email->getTextBody();

        $this->assertNotSame('', trim($text), 'The email has no plain-text part');
        $this->assertNotEmpty($email->getHtmlBody(), 'The email has no HTML part');
        $this->assertSame($text, strip_tags($text), 'The plain-text part contains HTML tags');
        $this->assertStringNotContainsString('&amp;', $text);
        $this->assertStringNotContainsString('&#039;', $text);

        foreach ([$email->getSubject(), $text] as $part) {
            $this->assertDoesNotMatchRegularExpression('/google|gmail/i', $part);
        }

        return $text;
    }

    public function test_sign_in_code_email(): void
    {
        $this->post('/auth/google/email', ['gmail' => 'new.person@example.com']);

        $email = $this->onlySentEmail();
        $text = $this->assertPlainAndBrandSafe($email);

        $this->assertSame('Your Certicode Labs sign-in code', $email->getSubject());
        $this->assertMatchesRegularExpression('/Your verification code: \d{6}\b/', $text);
    }

    public function test_email_confirmation_code_email_greets_by_name(): void
    {
        $this->actingAs($this->user)->post('/settings/gmail/connect', ['gmail' => 'john.doe@gmail.com']);

        $email = $this->onlySentEmail();
        $text = $this->assertPlainAndBrandSafe($email);

        $this->assertSame('Confirm your email for Certicode Labs', $email->getSubject());
        $this->assertStringContainsString('Hello John Doe,', $text);
        $this->assertMatchesRegularExpression('/Your verification code: \d{6}\b/', $text);
    }

    public function test_password_reset_email_carries_a_usable_plain_link(): void
    {
        $this->post('/forgot-password', ['email' => 'john@example.com']);

        $email = $this->onlySentEmail();
        $text = $this->assertPlainAndBrandSafe($email);

        $this->assertSame('Reset your Certicode Labs password', $email->getSubject());
        $this->assertStringContainsString('Hello John Doe,', $text);
        $this->assertMatchesRegularExpression('#^https?://\S+/reset-password/\S+email=john%40example\.com$#m', $text);
    }

    public function test_alert_email_has_a_plain_text_part_with_typed_names_unescaped(): void
    {
        $this->user->update(['gmail' => 'john.doe@gmail.com', 'gmail_verified_at' => now()]);

        $this->user->notify(new ClassActivityNotification(
            'New lab',
            "O'Brien & Lee opened a lab",
            'https://certicode.test/classes/1',
            'info'
        ));

        $text = (string) $this->onlySentEmail()->getTextBody();

        $this->assertStringContainsString("O'Brien & Lee opened a lab", $text);
        // Alerts keep only the path and rebuild the link on the app's own host
        $this->assertStringContainsString(url('/classes/1'), $text);
        $this->assertStringContainsString('You can turn them off in Settings.', $text);
        $this->assertSame($text, strip_tags($text));
    }

    public function test_password_reset_email_from_the_code_sign_in_flow(): void
    {
        $this->withSession(['google_auth_gmail' => 'john@example.com'])->post('/auth/google/forgot-password');

        $email = $this->onlySentEmail();
        $text = $this->assertPlainAndBrandSafe($email);

        $this->assertSame('Reset your Certicode Labs password', $email->getSubject());
        $this->assertStringContainsString('Hello John Doe,', $text);
        $this->assertStringContainsString('/reset-password/', $text);
    }
}
