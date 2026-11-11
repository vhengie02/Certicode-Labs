<?php

namespace App\Notifications;

use App\Notifications\Concerns\BuildsBrandedMail;
use App\Notifications\Concerns\ChecksEmailDelivery;
use App\Support\InternalUrl;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ClassActivityNotification extends Notification
{
    use BuildsBrandedMail, ChecksEmailDelivery, Queueable;

    protected string $title;

    protected string $message;

    protected string $url;

    protected string $type;

    /**
     * Create a new notification instance.
     */
    public function __construct(string $title, string $message, string $url, string $type = 'info')
    {
        $this->title = $title;
        $this->message = $message;
        // Stored as a path so it works on any host the app runs on (see InternalUrl)
        $this->url = InternalUrl::path($url);
        $this->type = $type; // class, module, lab, grade, certificate, info
    }

    /**
     * Get the notification's delivery channels.
     */
    public function via(object $notifiable): array
    {
        $channels = ['database'];

        if ($this->canEmail($notifiable) && $this->wantedByEmail($notifiable)) {
            $channels[] = 'mail';
        }

        return $channels;
    }

    private function wantedByEmail(object $notifiable): bool
    {
        return match ($this->type) {
            'class' => $notifiable->notify_class ?? true,
            'module' => $notifiable->notify_module ?? true,
            'lab', 'laboratory' => $notifiable->notify_lab ?? true,
            'grade' => $notifiable->notify_grade ?? true,
            'certificate' => $notifiable->notify_certificate ?? true,
            'info' => true,
            default => false,
        };
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        return $this->brandedMail($notifiable, $this->title, $this->message, 'View details', $this->url === '#' ? url('/') : url($this->url));
    }

    /**
     * Get the array representation of the notification.
     */
    public function toArray(object $notifiable): array
    {
        return [
            'title' => $this->title,
            'message' => $this->message,
            'url' => $this->url,
            'type' => $this->type,
        ];
    }
}
