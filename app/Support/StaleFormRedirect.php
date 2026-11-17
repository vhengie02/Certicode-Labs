<?php

namespace App\Support;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Session\TokenMismatchException;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Signing in or out swaps the CSRF token, and a session ends after SESSION_LIFETIME, so a page
 * left open in another tab or on a shared laptop posts an old token. That used to end on Laravel's
 * dead-end "419 Page Expired". The request is still refused and nothing it asked for runs; the
 * browser goes back to a fresh copy of the form, with what was typed (except passwords) kept.
 */
class StaleFormRedirect
{
    private const NEVER_FLASH = ['_token', 'password', 'password_confirmation', 'current_password'];

    public static function render(HttpException $e, Request $request): ?RedirectResponse
    {
        if ($e->getStatusCode() !== 419
            || ! $e->getPrevious() instanceof TokenMismatchException
            || $request->expectsJson()) {
            return null;
        }

        if ($request->routeIs('logout')) {
            // The session the page came from is gone, so the user is signed out already
            if (! Auth::check()) {
                return redirect('/')->with('success', 'Logged out successfully!');
            }

            return redirect()->to(self::previousUrl($request))
                ->with('error', 'That page was out of date. Please sign out again.');
        }

        return redirect()->to(self::previousUrl($request))
            ->withInput($request->except(self::NEVER_FLASH))
            ->with('error', 'That page was open too long, so it wasn\'t sent. Please try again.');
    }

    /**
     * The page the form was on, or the home page when the Referer is missing or another site's.
     */
    private static function previousUrl(Request $request): string
    {
        $referer = $request->headers->get('referer');

        if ($referer && parse_url($referer, PHP_URL_HOST) === $request->getHost()) {
            return $referer;
        }

        return url('/');
    }
}
