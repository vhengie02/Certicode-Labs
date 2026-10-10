<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

$app = Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->trustProxies(at: '*');
        // First, so its numbers include session start and auth lookups
        $middleware->prepend(\App\Http\Middleware\ServerTiming::class);
        $middleware->alias([
            'lab.session' => \App\Http\Middleware\AuthorizeLabSession::class,
            'admin' => \App\Http\Middleware\EnsureAdmin::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Error details come from Laravel's own handler only when APP_DEBUG is true.
    })->create();

// Auto-detect serverless environment (e.g. Vercel) where base storage is read-only
if (file_exists('/tmp') && (!is_writable(dirname(__DIR__) . '/storage') || isset($_ENV['VERCEL']) || isset($_SERVER['VERCEL']))) {
    $app->useStoragePath('/tmp/storage');
}

return $app;
