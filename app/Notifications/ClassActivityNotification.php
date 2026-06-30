<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class ClassActivityNotification extends Notification
{
    use Queueable;

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
        $this->url = $url;
        $this->type = $type; // class, module, lab
    }

    /**
     * Get the notification's delivery channels.
     */
    public function via(object $notifiable): array
    {
        $channels = ['database'];

        // Send via email if email notifications are enabled, and the user has either a verified Gmail or a primary email address
        $wantsEmail = $notifiable->notify_email_channel ?? true;
        $hasEmail = !empty($notifiable->gmail) ? !empty($notifiable->gmail_verified_at) : !empty($notifiable->email);

        if ($wantsEmail && $hasEmail) {
            $type = $this->type ?? 'info';
            $shouldSend = false;

            if ($type === 'class' && ($notifiable->notify_class ?? true)) {
                $shouldSend = true;
            } elseif ($type === 'module' && ($notifiable->notify_module ?? true)) {
                $shouldSend = true;
            } elseif (($type === 'lab' || $type === 'laboratory') && ($notifiable->notify_lab ?? true)) {
                $shouldSend = true;
            } elseif ($type === 'certificate' && ($notifiable->notify_certificate ?? true)) {
                $shouldSend = true;
            } elseif ($type === 'info' || $type === 'info') {
                $shouldSend = true;
            }

            if ($shouldSend) {
                $channels[] = 'mail';
            }
        }

        return $channels;
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): \Illuminate\Notifications\Messages\MailMessage
    {
        return (new \Illuminate\Notifications\Messages\MailMessage)
            ->subject("Certicode Alert: " . $this->title)
            ->greeting("Hello " . $notifiable->name . ",")
            ->line($this->message)
            ->action('View Details on Certicode', $this->url)
            ->line('Thank you for participating in Certicode Labs!')
            ->salutation('Best regards, Certicode Labs Team');
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
