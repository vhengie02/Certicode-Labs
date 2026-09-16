<?php

/**
 * Vercel Serverless Function Bridge for Laravel
 * 
 * Vercel's serverless environment provides a read-only filesystem except for /tmp.
 * This file initializes writable directories in /tmp and forwards the incoming request
 * to Laravel's standard public/index.php entrypoint.
 */

$tmpDirs = [
    '/tmp/views',
    '/tmp/storage/framework/views',
    '/tmp/storage/framework/cache',
    '/tmp/storage/framework/sessions',
    '/tmp/storage/logs',
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
putenv('VIEW_COMPILED_PATH=/tmp/views');

// If using default sqlite database, copy baseline database to writable /tmp
$sqliteSource = __DIR__ . '/../database/database.sqlite';
if (!file_exists('/tmp/database.sqlite') && file_exists($sqliteSource)) {
    @copy($sqliteSource, '/tmp/database.sqlite');
}

// Forward request to Laravel public entrypoint
require __DIR__ . '/../public/index.php';
