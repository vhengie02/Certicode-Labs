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

    /** Phones and tablets can't run VS Code, so students can read a lab there but not start it. */
    private const HANDHELDS = ['iPhone', 'iPad', 'iPod', 'Android', 'Mobile'];

    /**
     * True for a phone or tablet browser. An iPad asking for the desktop site sends a Mac user
     * agent, so the lab page also checks for a touch screen in the browser.
     */
    public static function isHandheld(?string $userAgent): bool
    {
        foreach (self::HANDHELDS as $needle) {
            if (str_contains((string) $userAgent, $needle)) {
                return true;
            }
        }

        return false;
    }

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
