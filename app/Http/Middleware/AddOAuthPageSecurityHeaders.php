<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * The headers a credential page needs, on the MCP OAuth sign-in and consent pages (routes/oauth.php).
 *
 * - Never framed (`frame-ancestors 'none'`, `X-Frame-Options: DENY`), so no other site can overlay the page
 *   to harvest a password or a click.
 * - A per-response CSP nonce for the page's one style block, shared with the views as `$cspNonce`; nothing
 *   else may load.
 * - `form-action` allows this origin plus the client's redirect origin: browsers apply it to the redirect
 *   that follows a form post, and the consent post ends at the client.
 * - Not cached and no referrer, so a page carrying an authorization request is not stored or leaked.
 */
class AddOAuthPageSecurityHeaders
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
        $nonce = Str::random(32);
        View::share('cspNonce', $nonce);

        $response = $next($request);

        if (!str_contains((string) $response->headers->get('Content-Type'), 'text/html')) {
            return $response;
        }

        $formActions = ["'self'"];
        $redirectOrigin = $this->originOf($request->query('redirect_uri'));

        if ($redirectOrigin !== null) {
            $formActions[] = $redirectOrigin;
        }

        $response->headers->set('Content-Security-Policy', implode('; ', [
            "default-src 'none'",
            "style-src 'nonce-{$nonce}'",
            "script-src 'none'",
            "img-src 'self'",
            'form-action ' . implode(' ', $formActions),
            "frame-ancestors 'none'",
            "base-uri 'none'",
        ]));
        $response->headers->set('X-Frame-Options', 'DENY');
        $response->headers->set('Cache-Control', 'no-store');
        $response->headers->set('Referrer-Policy', 'no-referrer');

        return $response;
    }

    /**
     * The scheme://host[:port] of a URI, or `null`.
     *
     * @param mixed $uri
     *
     * @return string|null
     */
    private function originOf(mixed $uri): ?string
    {
        if (!is_string($uri)) {
            return null;
        }

        $parts = parse_url($uri);

        if ($parts === false || !isset($parts['scheme'], $parts['host'])) {
            return null;
        }

        return $parts['scheme'] . '://' . $parts['host'] . (isset($parts['port']) ? ':' . $parts['port'] : '');
    }

}
