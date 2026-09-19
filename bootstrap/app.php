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
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (\Throwable $e, \Illuminate\Http\Request $request) {
            if ($request->has('debug')) {
                return response(
                    "Server Exception Details:\n" .
                    "-------------------------\n" .
                    "Type:    " . get_class($e) . "\n" .
                    "Message: " . $e->getMessage() . "\n" .
                    "File:    " . $e->getFile() . ':' . $e->getLine() . "\n\n" .
                    "Stack Trace:\n" . $e->getTraceAsString(),
                    500,
                    ['Content-Type' => 'text/plain']
                );
            }
        });
    })->create();

// Auto-detect serverless environment (e.g. Vercel) where base storage is read-only
if (file_exists('/tmp') && (!is_writable(dirname(__DIR__) . '/storage') || isset($_ENV['VERCEL']) || isset($_SERVER['VERCEL']))) {
    $app->useStoragePath('/tmp/storage');
}

return $app;
