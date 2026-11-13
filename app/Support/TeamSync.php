<?php

namespace App\Support;

/**
 * Settings for shared typing on group labs: teammates' edits travel between their editors
 * over Supabase Realtime. Only public values are involved: the project URL and its
 * publishable key. Without both, group labs work with each teammate in their own copy.
 */
class TeamSync
{
    public static function enabled(): bool
    {
        return ! empty(config('services.team_sync.supabase_url'))
            && ! empty(config('services.team_sync.publishable_key'));
    }

    /**
     * @return array{supabase_url: string, publishable_key: string, min_send_interval_ms: int}
     */
    public static function clientConfig(): array
    {
        return [
            'supabase_url' => rtrim((string) config('services.team_sync.supabase_url'), '/'),
            'publishable_key' => (string) config('services.team_sync.publishable_key'),
            'min_send_interval_ms' => max(50, (int) config('services.team_sync.min_send_interval_ms', 400)),
        ];
    }
}
