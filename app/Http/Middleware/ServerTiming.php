<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

/**
 * Adds a Server-Timing header (shown in the browser's Network tab under "Timing") that
 * splits each request into framework boot, database queries and everything else. It
 * shows where a slow page spends its time before deciding what to defer or cache.
 */
class ServerTiming
{
    public function handle(Request $request, Closure $next): Response
    {
        $start = microtime(true);
        $dbMs = 0.0;
        $queries = 0;

        DB::listen(function ($query) use (&$dbMs, &$queries) {
            $dbMs += $query->time;
            $queries++;
        });

        $response = $next($request);

        $totalMs = (microtime(true) - $start) * 1000;
        $metrics = [];
        if (defined('LARAVEL_START')) {
            $metrics[] = sprintf('boot;dur=%.1f;desc="Framework boot"', ($start - LARAVEL_START) * 1000);
        }
        $metrics[] = sprintf('db;dur=%.1f;desc="%d %s"', $dbMs, $queries, $queries === 1 ? 'query' : 'queries');
        $metrics[] = sprintf('app;dur=%.1f;desc="Everything else"', max(0, $totalMs - $dbMs));

        $response->headers->set('Server-Timing', implode(', ', $metrics), false);

        return $response;
    }
}
