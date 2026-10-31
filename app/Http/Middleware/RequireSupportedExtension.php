<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Turns away VS Code extension builds the server no longer supports with 426, so the
 * extension can tell the student to update instead of retrying forever.
 *
 * Builds since 1.6.0 send X-Extension-Version. Builds 1.3.0 to 1.5.0 send X-Session-Token
 * but no version, and still work, so they pass. Builds before 1.3.0 send the session token
 * as "Authorization: Bearer", which AuthorizeLabSession never accepts, so they get 426.
 * Signed-in browser requests (the lab page) are never checked.
 */
class RequireSupportedExtension
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user()) {
            return $next($request);
        }

        $version = trim((string) $request->header('X-Extension-Version', ''));
        $minVersion = (string) config('services.vscode_extension.required_version', '0.0.0');

        $outdated = $version !== ''
            ? ! preg_match('/^\d+(\.\d+){0,3}$/', $version) || version_compare($version, $minVersion, '<')
            : ! $request->headers->has('X-Session-Token') && $request->bearerToken() !== null;

        if ($outdated) {
            return response()->json([
                'error' => 'extension_outdated',
                'message' => 'This version of the Certicode extension is no longer supported. Install the latest version to continue.',
                'min_version' => $minVersion,
                'download_url' => asset(config('services.vscode_extension.download_path')),
            ], 426);
        }

        return $next($request);
    }
}
