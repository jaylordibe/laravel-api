<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Laravel\Mcp\Server\Registrar;

/**
 * The MCP OAuth discovery documents (RFC 8414 authorization server metadata, RFC 9728 protected resource
 * metadata), in place of laravel/mcp's `Mcp::oauthRoutes()`.
 *
 * Every URL is built from the pinned issuer (`mcp.authorization_server`, which is APP_URL), never from the
 * request: laravel/mcp's versions build the endpoint URLs from the Host header, so a shared cache in front of
 * the API could serve every client a token endpoint on another host. Only `/api/mcp` has a resource
 * document; any other path is 404.
 */
class McpOAuthMetadataController extends Controller
{

    /**
     * RFC 8414 authorization server metadata.
     *
     * @return JsonResponse
     */
    public function authorizationServer(): JsonResponse
    {
        $issuer = self::issuer();

        return new JsonResponse([
            'issuer' => $issuer,
            'authorization_endpoint' => "{$issuer}/oauth/authorize",
            'token_endpoint' => "{$issuer}/oauth/token",
            'registration_endpoint' => "{$issuer}/oauth/register",
            'response_types_supported' => ['code'],
            'code_challenge_methods_supported' => ['S256'],
            'scopes_supported' => [Registrar::OAUTH_SCOPE],
            'grant_types_supported' => ['authorization_code', 'refresh_token'],
            'token_endpoint_auth_methods_supported' => ['none'],
        ]);
    }

    /**
     * RFC 9728 protected resource metadata for /api/mcp, at the root and path-inserted locations.
     *
     * @return JsonResponse
     */
    public function protectedResource(): JsonResponse
    {
        $issuer = self::issuer();

        return new JsonResponse([
            'resource' => "{$issuer}/api/mcp",
            'authorization_servers' => [$issuer],
            'scopes_supported' => [Registrar::OAUTH_SCOPE],
            'bearer_methods_supported' => ['header'],
        ]);
    }

    /**
     * The pinned issuer, without a trailing slash.
     *
     * @return string
     */
    private static function issuer(): string
    {
        return rtrim((string) config('mcp.authorization_server'), '/');
    }

}
