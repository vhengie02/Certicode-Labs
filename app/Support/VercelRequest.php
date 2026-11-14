<?php

namespace App\Support;

/**
 * On Vercel every request runs the api/index.php function. For a path that starts with /api
 * (the VS Code extension's /api/v1/... calls, the /api/cron/tick cron) Symfony matches the
 * script's folder against the URI and takes "/api" as the app's base path. Laravel then routes
 * the rest of the path, which the v1 and cron routes in routes/web.php rely on, but every URL it
 * builds in that request keeps the prefix: a notification sent on submit opened /api/sessions/{id},
 * and that page loaded its stylesheet from /api/build/..., which 404s.
 *
 * Strip the prefix from the URI and present the script as /index.php at the site root, the way
 * public/index.php runs locally. Routing sees the same path as before and URLs start at the root.
 */
class VercelRequest
{
    private const FUNCTION_PREFIX = '/api';

    /**
     * @param  array<string, mixed>  $server  the $_SERVER of a request to api/index.php
     * @return array<string, mixed>
     */
    public static function stripFunctionPrefix(array $server): array
    {
        $uri = (string) ($server['REQUEST_URI'] ?? '/');
        $prefix = self::FUNCTION_PREFIX;

        if ($uri === $prefix || str_starts_with($uri, $prefix.'/') || str_starts_with($uri, $prefix.'?')) {
            $server['REQUEST_URI'] = '/'.ltrim(substr($uri, strlen($prefix)), '/');
        }

        $server['SCRIPT_NAME'] = '/index.php';
        $server['PHP_SELF'] = '/index.php';

        return $server;
    }
}
