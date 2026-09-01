<?php

namespace App\Http\Middleware;

use App\Utils\ResponseUtil;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Gates the Scramble docs routes (`/docs/api` and `/docs/api.json`).
 *
 * Replaces the package's Dedoc\Scramble\Http\Middleware\RestrictedDocsAccess, which allows the
 * `local` environment and otherwise defers to a `viewApiDocs` gate. That gate can never pass in
 * this application: the docs routes run in the `web` middleware group, and this API has no session
 * login at all -- routes/web.php is a redirect and a local-only PDF sample, and the only real guard
 * is `api` (Passport bearer). So the request always arrives as a guest and Gate::allows() always
 * denies. Opening the docs on a deployed environment therefore means adding authentication, not
 * defining a gate. This is where it differs from the Horizon dashboard, whose `viewHorizon` gate in
 * HorizonServiceProvider works precisely because Horizon's own routes authenticate a real user
 * first.
 *
 * The authentication here is a shared HTTP Basic credential rather than a platform account, on
 * purpose. Reading documentation is not an act by a user of the product, the credential is meant to
 * be handed to a downstream client developer who may have no account at all, and authenticating a
 * real user would mean introducing a session-login path to an API that deliberately has none.
 *
 * Three rules, in order:
 *
 *   1. `local` is open, unchanged -- day-to-day development sees no prompt.
 *   2. `production` is denied unconditionally, and deliberately not by a config flag. The document
 *      names every endpoint, parameter and role scope in the system, and marks which routes are
 *      reachable without a token -- a map worth handing an attacker. A credential that leaks out of
 *      a lower environment must buy nothing on production, so turning it on there is a code change
 *      and a review, not an env edit on a bad afternoon.
 *   3. Everywhere else (staging, review apps, and any environment that is neither of the above) is
 *      closed until `API_DOCS_ENABLED` is true AND both credentials are configured AND the request
 *      presents them. Every one of those is fail-closed: an environment that sets none of them
 *      stays 403, which is what it did before this middleware existed.
 *
 * A FORK MAY WIDEN RULE 2, BUT SHOULD THINK FIRST. The two environment names are hardcoded because
 * they are the only two whose behaviour must not be an operator's decision. A fork whose production
 * environment is named something else -- `prod`, `live` -- must add that name here, or its
 * production docs are gated by nothing but an env var.
 */
class RestrictApiDocsAccess
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
        if (app()->environment('local')) {
            return $next($request);
        }

        if (app()->environment('production')) {
            return ResponseUtil::forbidden();
        }

        if (!config('custom.api_docs.enabled')) {
            return ResponseUtil::forbidden();
        }

        $username = (string) config('custom.api_docs.username');
        $password = (string) config('custom.api_docs.password');

        // An unconfigured credential must never be matchable. Without this, an environment that
        // sets API_DOCS_ENABLED but forgets the username and password would let anyone in by
        // sending two empty strings.
        if ($username === '' || $password === '') {
            return ResponseUtil::forbidden();
        }

        if (!$this->hasValidCredentials($request, $username, $password)) {
            return $this->challenge();
        }

        return $next($request);
    }

    /**
     * Check the request's HTTP Basic credentials against the configured pair.
     *
     * Both halves are compared with hash_equals and neither comparison is short-circuited, so the
     * time taken does not reveal whether the username alone was right.
     *
     * @param Request $request
     * @param string $username
     * @param string $password
     *
     * @return bool
     */
    private function hasValidCredentials(Request $request, string $username, string $password): bool
    {
        $suppliedUsername = (string) $request->getUser();
        $suppliedPassword = (string) $request->getPassword();

        $usernameMatches = hash_equals($username, $suppliedUsername);
        $passwordMatches = hash_equals($password, $suppliedPassword);

        return $usernameMatches && $passwordMatches;
    }

    /**
     * Ask the browser for credentials.
     *
     * The WWW-Authenticate header is what turns a bare 401 into the browser's native login prompt;
     * once entered, the browser replays the credential on the same origin, so the docs UI's own
     * fetch of `/docs/api.json` is authenticated without a second prompt. The body stays
     * ResponseUtil's `{"message": ...}` envelope so these routes answer in the same shape as
     * everything else, rather than in Symfony's default HTML.
     *
     * @return Response
     */
    private function challenge(): Response
    {
        return ResponseUtil::unauthorized()->withHeaders([
            'WWW-Authenticate' => 'Basic realm="API Docs", charset="UTF-8"'
        ]);
    }

}
