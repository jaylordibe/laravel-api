<?php

namespace App\Http\Middleware;

use App\Utils\McpConfigUtil;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Applies this application's redirect rule to MCP OAuth client registration (POST /oauth/register),
 * before laravel/mcp's own registration controller sees the request.
 *
 * Registration is open, as the MCP specification expects: any client may register, and registering grants
 * nothing until a user signs in and approves it. What it must not do is let a code be sent somewhere the
 * MCP spec forbids: every redirect has to be loopback (http://localhost, 127.0.0.1, [::1]) or https, and
 * `custom.mcp.oauth.redirect_domains` narrows the https hosts when set. laravel/mcp's default would also
 * accept plain http to any host.
 */
class ValidateMcpClientRegistration
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
        $redirectUris = $request->input('redirect_uris');
        $allowedOrigins = McpConfigUtil::parseRedirectOrigins((string) config('custom.mcp.oauth.redirect_domains')) ?? [];

        $isPermitted = is_array($redirectUris) && $redirectUris !== [] && collect($redirectUris)->every(
            fn (mixed $uri): bool => is_string($uri) && McpConfigUtil::isPermittedRedirectUri($uri, $allowedOrigins)
        );

        if (!$isPermitted) {
            return new JsonResponse([
                'error' => 'invalid_redirect_uri',
                'error_description' => 'Every redirect URI must be http://localhost (or 127.0.0.1, [::1]) or an https URL this server allows.',
            ], Response::HTTP_BAD_REQUEST);
        }

        return $next($request);
    }

}
