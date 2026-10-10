<?php

namespace App\Support;

/**
 * Links stored in notifications point inside this app, but older ones were saved as full
 * URLs on whichever host created them (http://localhost, the Vercel domain, ...). Opening
 * them on a different host lands on a page where the user isn't signed in. Keep only the
 * path, so the link opens on the site the user is actually using.
 */
class InternalUrl
{
    public static function path(?string $url): string
    {
        $url = trim((string) $url);
        if ($url === '' || $url === '#') {
            return '#';
        }

        // Already a root-relative path (but not protocol-relative "//host")
        if (str_starts_with($url, '/') && !str_starts_with($url, '//')) {
            return $url;
        }

        $parts = parse_url($url);
        if ($parts === false || !in_array(strtolower($parts['scheme'] ?? ''), ['http', 'https'], true)) {
            return '#';
        }

        return ($parts['path'] ?? '/')
            . (isset($parts['query']) ? '?' . $parts['query'] : '')
            . (isset($parts['fragment']) ? '#' . $parts['fragment'] : '');
    }
}
