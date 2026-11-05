<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Facades\Cache;

/**
 * What the notification bell shows: the counts and the latest few notifications. The fetch,
 * mark-as-read and delete routes all answer with this, so the dropdown renders one shape.
 */
class NotificationFeed
{
    public static function for(User $user): array
    {
        return [
            'unreadCount' => $user->unreadNotifications()->count(),
            'readCount' => $user->readNotifications()->count(),
            'notifications' => $user->notifications()->take(5)->get()->map(fn ($notif) => [
                'id' => $notif->id,
                'unread' => $notif->unread(),
                'url' => InternalUrl::path($notif->data['url'] ?? null),
                'title' => $notif->data['title'] ?? 'Notification',
                'message' => $notif->data['message'] ?? '',
                'type' => $notif->data['type'] ?? 'info',
                'time' => $notif->created_at->diffForHumans(),
            ])->all(),
        ];
    }

    public static function forget(User $user): void
    {
        Cache::forget("user_notifs_{$user->id}");
        Cache::store('file')->forget("user_notifs_summary_{$user->id}");
    }
}
