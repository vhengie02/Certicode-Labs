<?php

namespace App\Support;

/**
 * A short name for a browser session, such as "Chrome on Windows". Order matters: Edge and Opera
 * also claim to be Chrome, Chrome claims to be Safari, and Android claims to be Linux.
 */
class DeviceLabel
{
    private const BROWSERS = [
        'Edg/' => 'Edge',
        'OPR/' => 'Opera',
        'Brave' => 'Brave',
        'Firefox/' => 'Firefox',
        'Chrome/' => 'Chrome',
        'Safari/' => 'Safari',
    ];

    private const SYSTEMS = [
        'Windows' => 'Windows',
        'iPhone' => 'iPhone',
        'iPad' => 'iPad',
        'Android' => 'Android',
        'Mac OS X' => 'macOS',
        'CrOS' => 'ChromeOS',
        'Linux' => 'Linux',
    ];

    public static function from(?string $userAgent): string
    {
        $browser = self::firstMatch((string) $userAgent, self::BROWSERS);
        $system = self::firstMatch((string) $userAgent, self::SYSTEMS);

        if ($browser && $system) {
            return "{$browser} on {$system}";
        }

        return $browser ?? $system ?? 'Unknown device';
    }

    private static function firstMatch(string $userAgent, array $names): ?string
    {
        foreach ($names as $needle => $name) {
            if (str_contains($userAgent, $needle)) {
                return $name;
            }
        }

        return null;
    }
}
