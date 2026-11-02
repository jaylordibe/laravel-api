<?php

namespace App\Http\Middleware;

use App\Utils\ResponseUtil;
use Closure;
use Illuminate\Http\Request;
use App\Utils\McpTokenUtil;
use Symfony\Component\HttpFoundation\Response;

/**
 * Refuses an MCP access token on the REST API.
 *
 * Passport's `auth:api` guard accepts any valid token from any active client, so without this an MCP
 * client the user approved for tool calls would hold the user's whole REST surface. What counts as an MCP
 * token is McpTokenUtil's rule. Registered right after `auth:api` on the authenticated REST group
 * (routes/api.php); AccessControlFeatureTest fails if a REST route loses it.
 */
class RejectMcpToken
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
        if (McpTokenUtil::isMcpToken($request->user()?->token())) {
            return ResponseUtil::unauthorized();
        }

        return $next($request);
    }

}
