<?php

namespace App\Notifications\Concerns;

trait ChecksEmailDelivery
{
    /**
     * Whether an email can reach this user: they haven't turned email off, they have a usable
     * address (an unverified Gmail doesn't count), and this environment can send mail.
     */
    protected function canEmail(object $notifiable): bool
    {
        $wantsEmail = $notifiable->notify_email_channel ?? true;
        $hasEmail = ! empty($notifiable->gmail) ? ! empty($notifiable->gmail_verified_at) : ! empty($notifiable->email);

        return $wantsEmail && $hasEmail && $this->mailIsConfigured();
    }

    private function mailIsConfigured(): bool
    {
        if (app()->environment('testing')) {
            return true;
        }

        $defaultMailer = config('mail.default');
        if ($defaultMailer === 'smtp') {
            $host = config('mail.mailers.smtp.host');
            $user = config('mail.mailers.smtp.username');

            return ! empty($host) && ! in_array($host, ['127.0.0.1', 'localhost']) && ! empty($user);
        }

        return ! empty($defaultMailer) && $defaultMailer !== 'array';
    }
}
