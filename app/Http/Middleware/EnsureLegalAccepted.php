<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Sends a signed-in user who hasn't accepted the current Terms and Privacy Policy to the page
 * that asks them to. This catches accounts made before the policies existed, sign-ups through
 * Google or GitHub, and anyone due to accept a new version.
 */
class EnsureLegalAccepted
{
    /**
     * Someone who won't accept must still be able to take their data and leave.
     */
    private const ALWAYS_ALLOWED = [
        'settings.show',
        'settings.export',
        'settings.account.destroy',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        // JSON calls are a page already open, such as a running lab: a redirect would break it
        if (! config('legal.enforce')
            || ! $user
            || $user->hasAcceptedTerms()
            || $request->expectsJson()
            || $request->routeIs(self::ALWAYS_ALLOWED)) {
            return $next($request);
        }

        if ($request->isMethod('GET')) {
            $request->session()->put('url.intended', $request->fullUrl());
        }

        return redirect()->route('legal.accept.show');
    }
}
