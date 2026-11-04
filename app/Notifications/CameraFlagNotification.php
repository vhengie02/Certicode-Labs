<?php

namespace App\Notifications;

use App\Notifications\Concerns\ChecksEmailDelivery;
use App\Support\InternalUrl;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Tells a lab's instructor that camera flags started coming in, so they hear about them even
 * with the monitoring page closed. Email is opt-in (notify_integrity): a lab can raise many camera
 * flags, though the sender already sends at most one per quiet spell.
 */
class CameraFlagNotification extends Notification
{
    use ChecksEmailDelivery, Queueable;

    public function __construct(
        protected string $title,
        protected string $message,
        protected string $url,
    ) {
        // Stored as a path so it works on any host the app runs on (see InternalUrl)
        $this->url = InternalUrl::path($url);
    }

    public function via(object $notifiable): array
    {
        return $this->canEmail($notifiable) && ($notifiable->notify_integrity ?? false)
            ? ['database', 'mail']
            : ['database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Certicode Alert: '.$this->title)
            ->greeting('Hello '.$notifiable->name.',')
            ->line($this->message)
            ->action('Open monitoring', url($this->url))
            ->salutation('Best regards, Certicode Labs Team');
    }

    public function toArray(object $notifiable): array
    {
        return [
            'title' => $this->title,
            'message' => $this->message,
            'url' => $this->url,
            'type' => 'lab',
        ];
    }
}
