<?php

namespace App\Http\Middleware;

use App\Utils\McpSignInUtil;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Refuses an MCP OAuth approval (or denial) from a sign-in that a revocation has overtaken, or that has waited
 * longer than the sign-in window: someone who signed in and left the consent page open must not be able to
 * authorize a client after the owner signed out everywhere or changed the password. See McpSignInUtil.
 *
 * On refusal the browser session ends and nothing is issued; the user starts again from their application.
 */
class EnsureFreshOAuthSignIn
{

    /**
     * Handle an incoming request.
     *
     * @param Request $request
     * @param Closure $next
     *
     * @return Response
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::guard('web')->user();

        if ($user !== null && McpSignInUtil::isSignInStillValid($request, (int) $user->getAuthIdentifier())) {
            return $next($request);
        }

        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->view('mcp.message', [
            'title' => 'Sign-in expired',
            'message' => 'This sign-in has expired or was ended. Start the connection again from your application.',
        ], Response::HTTP_FORBIDDEN);
    }

}
