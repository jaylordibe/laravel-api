<?php

namespace App\Http\Middleware;

use App\Utils\McpClientCredentialUtil;
use App\Utils\ResponseUtil;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Authenticates an MCP integration client on /api/mcp before any MCP message is processed.
 *
 * An MCP client (n8n, Claude Code, ...) is a different principal from a user: it presents its own
 * bearer token, whose SHA-256 is listed in `custom.mcp.client_tokens`. Passport (`auth:api`) is not
 * involved, so a user's access token is not accepted here and an MCP token is accepted nowhere
 * else. Checks, in order:
 *
 *   1. No client configured (or the value is malformed) -> 404, as if the route did not exist.
 *   2. An `Origin` header not in `custom.mcp.allowed_origins` -> 403. MCP transport security: a
 *      browser page must not be able to drive the endpoint. Server-side clients send no Origin.
 *   3. A missing or unknown bearer token -> 401 with `WWW-Authenticate: Bearer`.
 *
 * The authenticated client's name is stored on the request as the `mcp_client` attribute.
 */
class AuthenticateMcpClient
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
        $credentials = McpClientCredentialUtil::parseCredentials((string) config('custom.mcp.client_tokens'));

        if (empty($credentials)) {
            return ResponseUtil::notFound();
        }

        $origin = $request->headers->get('Origin');

        if ($origin !== null) {
            $allowedOrigins = McpClientCredentialUtil::parseOrigins((string) config('custom.mcp.allowed_origins')) ?? [];

            if (!in_array($origin, $allowedOrigins, true)) {
                return ResponseUtil::forbidden();
            }
        }

        $token = $this->bearerToken($request);

        if ($token === null) {
            return ResponseUtil::unauthorized()->header('WWW-Authenticate', 'Bearer realm="mcp"');
        }

        $clientName = McpClientCredentialUtil::findClientByToken($token, $credentials);

        if ($clientName === null) {
            return ResponseUtil::unauthorized()->header('WWW-Authenticate', 'Bearer realm="mcp", error="invalid_token"');
        }

        $request->attributes->set('mcp_client', $clientName);

        return $next($request);
    }

    /**
     * The bearer token, matched case-insensitively on the scheme, or `null`.
     *
     * @param Request $request
     *
     * @return string|null
     */
    private function bearerToken(Request $request): ?string
    {
        $header = (string) $request->headers->get('Authorization', '');

        if (!preg_match('/^bearer\s+(\S+)\s*$/i', $header, $matches)) {
            return null;
        }

        return $matches[1];
    }

}
