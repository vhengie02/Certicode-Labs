<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * On Vercel the VS Code extension's /api/v1 calls reach the web routes, so they pass through
 * StartSession. The extension authenticates with X-Session-Token and keeps no cookies, so
 * each call would start a new session and insert a row into the sessions table, several
 * times a minute per student. A token request with no session cookie gets a throwaway
 * in-memory session instead. Runs before StartSession.
 */
class SkipSessionForExtensionToken
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->headers->has('X-Session-Token') && !$request->cookies->has(config('session.cookie'))) {
            config(['session.driver' => 'array']);
        }

        return $next($request);
    }
}
