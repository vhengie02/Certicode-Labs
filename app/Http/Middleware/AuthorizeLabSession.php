<?php

namespace App\Http\Middleware;

use App\Models\LabSession;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Guards routes that take a {sessionId}. A request is allowed when it carries that
 * session's extension token (X-Session-Token, sent by the VS Code extension) or comes
 * from a signed-in user who may access the session (owner, teammate, instructor/admin).
 */
class AuthorizeLabSession
{
    public function handle(Request $request, Closure $next): Response
    {
        $session = LabSession::find((int) $request->route('sessionId'));
        if (!$session) {
            return response()->json(['error' => 'Session not found.'], 404);
        }

        $token = (string) $request->header('X-Session-Token', '');
        $tokenValid = $token !== '' && $session->extension_token && hash_equals($session->extension_token, $token);

        if (!$tokenValid && !$session->isAccessibleBy($request->user())) {
            return response()->json(['error' => 'unauthorized', 'message' => 'You do not have access to this lab session.'], $request->user() ? 403 : 401);
        }

        return $next($request);
    }
}
