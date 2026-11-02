<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Laravel\Mcp\Server\Registrar;
use Symfony\Component\HttpFoundation\Response;

/**
 * Holds GET /oauth/authorize to the MCP authorization rules Passport does not enforce itself.
 *
 *   1. PKCE with S256 only. league/oauth2-server also accepts `plain`, and reads a missing method as
 *      `plain`; the MCP spec requires S256.
 *   2. A `resource` parameter (RFC 8707), when sent, must name this server's MCP endpoint. Passport cannot
 *      bind a token to a resource, so a request for another one is refused rather than silently served.
 *   3. `prompt=login consent` and `scope=mcp:use`, whatever the client asked: the password and the consent page are asked for
 *      on every authorization. Passport otherwise reuses a signed-in browser session and skips consent when
 *      the client already holds a token, so a browser session left behind could mint a new token after the
 *      user signed out everywhere or changed their password.
 *
 * A refusal answers 400 with an OAuth error body and does not redirect, so it can never be turned into a
 * redirect to an unverified URI.
 */
class PrepareMcpAuthorizationRequest
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
        if ($request->query('code_challenge_method') !== 'S256' || !is_string($request->query('code_challenge'))) {
            return $this->refuse('invalid_request', 'PKCE with code_challenge_method=S256 is required.');
        }

        $resource = $request->query('resource');

        if ($resource !== null && rtrim((string) $resource, '/') !== url('/api/mcp')) {
            return $this->refuse('invalid_target', 'The requested resource is not served by this authorization server.');
        }

        $request->query->set('prompt', 'login consent');

        // Exactly the one scope the MCP endpoint requires, whatever the client asked for. Any other value is
        // replaced rather than passed on: Passport strips `*` from authorization-code tokens, which would
        // otherwise produce a scope-less token. Set here, on the authorization request only — never as
        // Passport's default scope, which would also stamp it on every personal access token REST sign-in
        // issues and let a REST token through to /api/mcp.
        $request->query->set('scope', Registrar::OAUTH_SCOPE);

        return $next($request);
    }

    /**
     * An OAuth error answer that does not redirect.
     *
     * @param string $error
     * @param string $description
     *
     * @return JsonResponse
     */
    private function refuse(string $error, string $description): JsonResponse
    {
        return new JsonResponse(['error' => $error, 'error_description' => $description], Response::HTTP_BAD_REQUEST);
    }

}
