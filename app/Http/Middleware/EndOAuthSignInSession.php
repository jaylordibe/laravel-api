<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Ends the browser session the MCP OAuth sign-in created, once the user has approved or denied.
 *
 * That session exists only to carry one sign-in from the password page to the consent page. Leaving it
 * behind would let whoever holds the browser authorize another client later without a password, even after
 * the user signed out everywhere or changed their password (revokeAllUserTokens ends tokens, not browser
 * sessions). Runs after Passport has issued its redirect.
 */
class EndOAuthSignInSession
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
        $response = $next($request);

        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return $response;
    }

}
