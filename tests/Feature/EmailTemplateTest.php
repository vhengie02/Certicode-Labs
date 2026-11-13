<?php

namespace Tests\Feature;

use App\Models\User;
use App\Notifications\CameraFlagNotification;
use App\Notifications\ClassActivityNotification;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * The shared email template carries verification codes and reset links. Mail clients strip
 * inline SVG and most CSS, so the template must hold up with neither.
 */
class EmailTemplateTest extends TestCase
{
    private function renderEmail(array $data): string
    {
        return view('emails.generic', $data)->render();
    }

    public function test_code_email_shows_the_code_and_no_button(): void
    {
        $html = $this->renderEmail([
            'title' => 'Gmail Connection Code',
            'greeting' => 'Hello Alex Mercer,',
            'messageLines' => ['You requested to connect your Gmail account.'],
            'code' => '201589',
        ]);

        $this->assertStringContainsString('Gmail Connection Code', $html);
        $this->assertStringContainsString('Hello Alex Mercer,', $html);
        $this->assertStringContainsString('>201589</span>', $html);
        $this->assertStringContainsString('Your verification code', $html);
        $this->assertStringNotContainsString('If the button doesn', $html);
    }

    public function test_link_email_shows_the_button_and_a_plain_link_and_no_code(): void
    {
        $url = 'https://certicode.test/reset-password/abc?email=a%40b.test';

        $html = $this->renderEmail([
            'title' => 'Reset Password Link',
            'messageLines' => ['We received a password reset request for your account.'],
            'actionUrl' => $url,
            'actionText' => 'Reset Password',
        ]);

        $this->assertStringContainsString('Reset Password &rarr;', $html);
        // Once on the button, once as the plain link under it, once as that link's text
        $this->assertSame(3, substr_count($html, e($url)));
        $this->assertStringNotContainsString('Your verification code', $html);
    }

    public function test_template_uses_nothing_mail_clients_strip(): void
    {
        $html = $this->renderEmail(['title' => 'Any', 'code' => '123456', 'actionUrl' => 'https://certicode.test/x']);

        $this->assertStringNotContainsString('<svg', $html);
        $this->assertStringNotContainsString('var(--', $html);
        $this->assertStringNotContainsString('rgba(', $html);
        $this->assertStringNotContainsString('<style', $html);
    }

    public function test_greeting_and_title_are_escaped(): void
    {
        $html = $this->renderEmail(['title' => '<b>Title</b>', 'greeting' => 'Hello <script>alert(1)</script>,']);

        $this->assertStringNotContainsString('<script>', $html);
        $this->assertStringNotContainsString('<b>Title</b>', $html);
    }

    public function test_class_activity_alert_uses_the_shared_template(): void
    {
        $user = new User(['name' => 'Amara Okafor', 'email' => 'amara@example.test']);

        $mail = (new ClassActivityNotification('New lab posted', 'Lab <b>Loops</b> is open in Java 101.', '/classes/7', 'lab'))->toMail($user);
        $html = (string) $mail->render();

        $this->assertSame('Certicode Alert: New lab posted', $mail->subject);
        $this->assertStringContainsString('Hello Amara Okafor,', $html);
        $this->assertStringContainsString('View details &rarr;', $html);
        $this->assertStringContainsString(e(url('/classes/7')), $html);
        $this->assertStringContainsString('alert emails are on', $html);
        // The sign-in footnote would be wrong on an alert
        $this->assertStringNotContainsString('ask for this?', $html);
        // Alert text carries names people typed, so it must not reach the page as HTML
        $this->assertStringNotContainsString('<b>Loops</b>', $html);
        $this->assertStringContainsString('&lt;b&gt;Loops&lt;/b&gt;', $html);
    }

    public function test_camera_flag_alert_uses_the_shared_template(): void
    {
        $user = new User(['name' => 'Diego Ramos', 'email' => 'diego@example.test']);

        $html = (string) (new CameraFlagNotification('Camera flags in Loops', 'Lin Wei has 3 camera flags.', '/labs/4/monitor'))->toMail($user)->render();

        $this->assertStringContainsString('Camera flags in Loops', $html);
        $this->assertStringContainsString('Open monitoring &rarr;', $html);
        $this->assertStringContainsString(e(url('/labs/4/monitor')), $html);
    }

    public function test_sent_alert_needs_no_image(): void
    {
        $user = new User(['name' => 'Diego Ramos', 'email' => 'diego@example.test']);

        $user->notifyNow(new CameraFlagNotification('Camera flags in Loops', 'Lin Wei has 3 camera flags.', '/labs/4/monitor'), ['mail']);

        $sent = app('mailer')->getSymfonyTransport()->messages();
        $this->assertCount(1, $sent);

        $email = $sent->first()->getOriginalMessage();
        $this->assertSame('Certicode Alert: Camera flags in Loops', $email->getSubject());
        $this->assertStringNotContainsString('<img', $email->getHtmlBody());
        $this->assertCount(0, $email->getAttachments());
    }

    /**
     * Gmail hides images on mail it distrusts, attached ones included, and the logo once showed
     * as an empty box. The mark is drawn with borders so it never depends on an image loading.
     */
    public function test_sent_email_draws_the_logo_without_an_image(): void
    {
        Mail::send('emails.generic', ['title' => 'Gmail Connection Code', 'code' => '201589'], function ($message) {
            $message->to('student@example.test')->subject('Certicode Labs: Gmail Connection Code');
        });

        $sent = app('mailer')->getSymfonyTransport()->messages();
        $this->assertCount(1, $sent);

        $email = $sent->first()->getOriginalMessage();
        $html = $email->getHtmlBody();
        $this->assertStringNotContainsString('<img', $html);
        $this->assertStringNotContainsString('cid:', $html);
        $this->assertCount(0, $email->getAttachments());
        $this->assertStringContainsString('border-radius: 50%', $html);
    }
}
