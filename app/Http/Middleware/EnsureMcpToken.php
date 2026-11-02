<?php

namespace App\Http\Middleware;

use App\Utils\McpConfigUtil;
use App\Utils\McpTokenUtil;
use App\Utils\ResponseUtil;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Authenticates the user an MCP client acts for on /api/mcp, before any MCP message is processed.
 *
 * The token is a Passport access token issued through the MCP OAuth sign-in (routes/oauth.php). REST
 * sign-in issues personal access tokens, which are refused here, and RejectMcpToken refuses an MCP token on
 * every REST route. Checks, in order:
 *
 *   1. An `Origin` header not in `custom.mcp.allowed_origins` -> 403. MCP transport security: a browser
 *      page must not be able to drive the endpoint. Server-side clients send no Origin.
 *   2. No valid Passport access token -> 401. The guard reloads the user on every request, so a revoked
 *      token, a deleted account or an expired token all fail here.
 *   3. A token that is not a usable MCP token (McpTokenUtil: issued through the MCP sign-in and carrying
 *      `mcp:use`) -> 401: it was not issued for this endpoint.
 *
 * Every 401 gets `WWW-Authenticate: Bearer ... resource_metadata=...` from laravel/mcp's global
 * AddWwwAuthenticateHeader: what tells a client where to sign in, and how a refused client starts again.
 * On success the `api` guard becomes the default, so tools read the user with `$request->user()`.
 */
class EnsureMcpToken
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
        $origin = $request->headers->get('Origin');

        if ($origin !== null) {
            $allowedOrigins = McpConfigUtil::parseOrigins((string) config('custom.mcp.allowed_origins')) ?? [];

            if (!in_array($origin, $allowedOrigins, true)) {
                return ResponseUtil::forbidden();
            }
        }

        $user = Auth::guard('api')->user();
        $token = $user?->token();

        if (!McpTokenUtil::isUsableOnMcp($token)) {
            return ResponseUtil::unauthorized();
        }

        Auth::shouldUse('api');

        return $next($request);
    }

}
