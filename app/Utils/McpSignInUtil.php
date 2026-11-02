<?php

namespace App\Utils;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

/**
 * Keeps an MCP OAuth sign-in from outliving a revocation.
 *
 * The sign-in page (OAuthLoginController) creates a browser session that carries one sign-in to the consent
 * page. Revoking a user's access (sign-out everywhere, a password change or reset, an email change) ends their
 * tokens and codes, but cannot reach a browser session. So the sign-in records when it happened, every
 * revocation records when it happened, and approving (EnsureFreshOAuthSignIn) is refused when the sign-in is
 * older than the latest revocation, or older than the sign-in window.
 *
 * The revocation time lives in the cache (shared across replicas; `app:check-config` refuses a per-container
 * cache in production) for as long as a sign-in could still be pending, so no schema change is needed.
 */
class McpSignInUtil
{

    /**
     * How long a sign-in may wait on the consent page, in minutes (the same window as nestjs-api's).
     */
    public const int SIGN_IN_WINDOW_MINUTES = 10;

    /**
     * The session key holding the sign-in time.
     */
    private const string SESSION_KEY = 'mcp_oauth_signed_in_at';

    /**
     * Record that this browser just signed in.
     *
     * @param Request $request
     *
     * @return void
     */
    public static function recordSignIn(Request $request): void
    {
        $request->session()->put(self::SESSION_KEY, now()->getTimestamp());
    }

    /**
     * Record that the user's access was just revoked.
     *
     * @param int $userId
     *
     * @return void
     */
    public static function recordRevocation(int $userId): void
    {
        Cache::put(self::revocationKey($userId), now()->getTimestamp(), now()->addMinutes(self::SIGN_IN_WINDOW_MINUTES + 5));
    }

    /**
     * Whether this browser's sign-in may still authorize a client for the user.
     *
     * A revocation in the same second as the sign-in counts against it: when the order cannot be told, the
     * sign-in loses.
     *
     * @param Request $request
     * @param int $userId
     *
     * @return bool
     */
    public static function isSignInStillValid(Request $request, int $userId): bool
    {
        $signedInAt = $request->session()->get(self::SESSION_KEY);

        if (!is_int($signedInAt) || $signedInAt < now()->subMinutes(self::SIGN_IN_WINDOW_MINUTES)->getTimestamp()) {
            return false;
        }

        $revokedAt = Cache::get(self::revocationKey($userId));

        return !is_int($revokedAt) || $revokedAt < $signedInAt;
    }

    /**
     * The cache key of a user's latest revocation time.
     *
     * @param int $userId
     *
     * @return string
     */
    private static function revocationKey(int $userId): string
    {
        return "mcp-oauth:revoked-at:{$userId}";
    }

}
