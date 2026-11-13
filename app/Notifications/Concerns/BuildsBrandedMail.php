<?php

namespace App\Notifications\Concerns;

use Illuminate\Notifications\Messages\MailMessage;

trait BuildsBrandedMail
{
    /**
     * An alert email in the shared template (emails.generic), so alerts look like the sign-in emails.
     */
    protected function brandedMail(object $notifiable, string $title, string $message, string $actionText, string $actionUrl): MailMessage
    {
        return (new MailMessage)
            ->subject('Certicode Alert: '.$title)
            ->view(['html' => 'emails.generic', 'text' => 'emails.generic-text'], [
                'title' => $title,
                'greeting' => 'Hello '.$notifiable->name.',',
                // The template prints lines as HTML, and alert text carries names people typed
                'messageLines' => [e($message)],
                'actionText' => $actionText,
                'actionUrl' => $actionUrl,
                'footnote' => 'You get this email because alert emails are on for your account. You can turn them off in Settings.',
            ]);
    }
}
