<?php

namespace App\Notifications;

use App\Support\InternalUrl;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/**
 * Tells a lab's instructor that camera flags started coming in, so they hear about them even
 * with the monitoring page closed. In-app only: a lab can raise many camera flags, and the
 * sender already sends at most one per quiet spell.
 */
class CameraFlagNotification extends Notification
{
    use Queueable;

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
        return ['database'];
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
