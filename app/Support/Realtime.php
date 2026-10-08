<?php

namespace App\Support;

/**
 * Client-side settings for live updates over Pusher Channels. Only public values are exposed:
 * the app key and cluster. The secret stays on the server and is used to sign private channels.
 */
class Realtime
{
    public static function enabled(): bool
    {
        return config('broadcasting.default') === 'pusher'
            && !empty(config('broadcasting.connections.pusher.key'))
            && !empty(config('broadcasting.connections.pusher.secret'));
    }

    /**
     * @return array{driver: string, key: string, cluster: string, ws_host: string}|null
     */
    public static function clientConfig(): ?array
    {
        if (!self::enabled()) {
            return null;
        }

        $cluster = (string) (config('broadcasting.connections.pusher.options.cluster') ?: 'mt1');

        return [
            'driver' => 'pusher',
            'key' => (string) config('broadcasting.connections.pusher.key'),
            'cluster' => $cluster,
            'ws_host' => "ws-{$cluster}.pusher.com",
        ];
    }

    /**
     * Sign a private-channel subscription for a socket (Pusher's auth response body).
     */
    public static function authorize(string $channel, string $socketId): array
    {
        $pusher = app(\Illuminate\Broadcasting\BroadcastManager::class)->connection('pusher')->getPusher();

        return json_decode($pusher->authorizeChannel($channel, $socketId), true);
    }
}
