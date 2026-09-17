<?php

use Illuminate\Foundation\Application;
use Illuminate\Http\Request;

define('LARAVEL_START', microtime(true));

/**
 * Vercel Serverless Function Bridge for Laravel
 *
 * Vercel lambda instances provide a read-only filesystem except for /tmp.
 * This bridge initializes writable directories in /tmp, points storage and caches there,
 * and handles the HTTP request through Laravel's application kernel.
 */

$tmpDirs = [
    '/tmp/views',
    '/tmp/storage',
    '/tmp/storage/app',
    '/tmp/storage/app/public',
    '/tmp/storage/framework',
    '/tmp/storage/framework/cache',
    '/tmp/storage/framework/cache/data',
    '/tmp/storage/framework/sessions',
    '/tmp/storage/framework/views',
    '/tmp/storage/logs',
    '/tmp/bootstrap',
    '/tmp/bootstrap/cache',
];

foreach ($tmpDirs as $dir) {
    if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
    }
}

// Point compiled views and caches to writable /tmp
putenv('APP_CONFIG_CACHE=/tmp/bootstrap/cache/config.php');
putenv('APP_EVENTS_CACHE=/tmp/bootstrap/cache/events.php');
putenv('APP_PACKAGES_CACHE=/tmp/bootstrap/cache/packages.php');
putenv('APP_ROUTES_CACHE=/tmp/bootstrap/cache/routes.php');
putenv('APP_SERVICES_CACHE=/tmp/bootstrap/cache/services.php');
putenv('VIEW_COMPILED_PATH=/tmp/storage/framework/views');
putenv('APP_MAINTENANCE_DRIVER=file');
putenv('SESSION_DRIVER=database');
putenv('APP_NAME=Certicode Labs');
putenv('SESSION_COOKIE=certicode_labs_session');

try {
    // Autoload Composer dependencies
    require __DIR__ . '/../vendor/autoload.php';

    // Bootstrap Laravel Application
    /** @var Application $app */
    $app = require_once __DIR__ . '/../bootstrap/app.php';

    // Explicitly set storage directory to writable /tmp/storage
    $app->useStoragePath('/tmp/storage');

    // Capture and handle the incoming HTTP request
    $request = Request::capture();
    $app->handleRequest($request);
} catch (\Throwable $e) {
    error_log("Vercel Serverless Fatal Exception: " . $e->getMessage() . "\n" . $e->getTraceAsString());

    if (headers_sent()) {
        exit;
    }

    $showDebug = (isset($_GET['debug']) || env('APP_DEBUG', false));
    http_response_code(500);

    if ($showDebug) {
        header('Content-Type: text/plain');
        echo "Laravel Serverless Execution Failure:\n";
        echo "-------------------------------------\n";
        echo "Exception: " . get_class($e) . "\n";
        echo "Message:   " . $e->getMessage() . "\n";
        echo "File:      " . $e->getFile() . ':' . $e->getLine() . "\n\n";
        echo "Stack Trace:\n" . $e->getTraceAsString();
    } else {
        header('Content-Type: text/html');
        echo '<!DOCTYPE html><html><head><title>500 Server Error</title><meta name="viewport" content="width=device-width,initial-scale=1"></head><body style="background:#0f172a;color:#cbd5e1;font-family:sans-serif;display:flex;align-items:center;justify-content:center;min-height:100vh;margin:0;"><div style="text-align:center;"><h1 style="font-size:2rem;margin-bottom:0.5rem;color:#f87171;">500 | Server Error</h1><p style="color:#94a3b8;">The server encountered an error while processing your request.</p><p style="font-size:0.85rem;color:#64748b;margin-top:1rem;">Add <code style="background:#1e293b;padding:2px 6px;border-radius:4px;color:#38bdf8;">?debug=1</code> to the URL to view diagnostic details.</p></div></body></html>';
    }
}
