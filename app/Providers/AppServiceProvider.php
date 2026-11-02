<?php

namespace App\Providers;

use App\Enums\UserPermission;
use App\Models\User;
use App\Repositories\SingleUseAuthCodeRepository;
use App\Repositories\SingleUseRefreshTokenRepository;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Support\ServiceProvider;
use Laravel\Passport\Bridge\AuthCodeRepository;
use Laravel\Passport\Bridge\RefreshTokenRepository;
use Laravel\Passport\Passport;

class AppServiceProvider extends ServiceProvider
{

    /**
     * Register any application services.
     *
     * Passport's own route file is not registered: REST sign-in issues tokens with `createToken()`, and the
     * MCP OAuth flow registers only the Passport routes it needs (routes/oauth.php).
     */
    public function register(): void
    {
        Passport::ignoreRoutes();

        // Codes and refresh tokens are single-use under concurrency, not only in sequence.
        $this->app->bind(AuthCodeRepository::class, SingleUseAuthCodeRepository::class);
        $this->app->bind(RefreshTokenRepository::class, SingleUseRefreshTokenRepository::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Disable the wrapping of the outermost resource
        JsonResource::withoutWrapping();

        // MCP OAuth tokens (routes/oauth.php): the only tokens issued through an authorization code.
        Passport::tokensExpireIn(now()->addMinutes(config('custom.mcp.oauth.access_token_ttl_minutes')));
        Passport::refreshTokensExpireIn(now()->addDays(config('custom.mcp.oauth.refresh_token_ttl_days')));
        Passport::authorizationView('mcp.authorize');
        // The reset link opens the client app, which posts the token back to /api/reset-password.
        ResetPassword::createUrlUsing(fn (User $user, string $token): string => config('custom.app_frontend_url')
            . '/reset-password?' . http_build_query(['token' => $token, 'email' => $user->getEmailForPasswordReset()]));

        // Personal access tokens: what sign-in issues through createToken(), so this is the session lifetime.
        Passport::personalAccessTokensExpireIn(now()->addMinutes(config('custom.auth.token_ttl_minutes')));

        // Public limiter for unauthenticated endpoints
        RateLimiter::for('public', function (Request $request) {
            return Limit::perMinute(config('custom.rate_limits.public'))->by('ip:' . $request->ip());
        });

        // Very strict limiter for highly sensitive endpoints
        RateLimiter::for('sensitive', function (Request $request) {
            return Limit::perMinute(config('custom.rate_limits.sensitive'))->by('ip:' . $request->ip());
        });

        // Authenticated routes that check or set a password (step-up): per USER, so a stolen token cannot
        // guess the password at the api rate, and one shared IP (office or carrier NAT) cannot use up the
        // budget of everyone behind it the way the IP-keyed `sensitive` limiter would.
        RateLimiter::for('step-up', function (Request $request) {
            // The IP fallback only matters if this ever ran before authentication: callers must not share one key.
            $key = $request->user()?->id ? 'user:' . $request->user()->id : 'ip:' . $request->ip();

            return Limit::perMinute(config('custom.rate_limits.sensitive'))->by('step-up:' . $key);
        });

        // Sign-in: per account and IP like Laravel's starter kits, plus an aggregate budget per IP so
        // switching identifiers cannot turn one address into an unlimited password spray.
        RateLimiter::for('sign-in', function (Request $request) {
            $identifier = $request->input('identifier');
            // A non-string identifier is a validation error, not a 500 from casting an array.
            $identifier = is_string($identifier) ? Str::lower(trim($identifier)) : '';

            return [
                Limit::perMinute(config('custom.rate_limits.sensitive'))->by("sign-in:{$identifier}|{$request->ip()}"),
                Limit::perMinute(config('custom.rate_limits.sign_in_per_ip'))->by("sign-in-ip:{$request->ip()}"),
            ];
        });

        // Primary limiter for authenticated endpoints, with multiple layers to mitigate different abuse scenarios
        RateLimiter::for('api', function (Request $request) {
            $user = $request->user();
            $tokenId = $user?->token()?->id;
            $userId = $user?->id;
            $ip = $request->ip();

            $tokenKey = $tokenId ? "token:$tokenId" : "ip:$ip";
            $userKey = $userId ? "user:$userId" : "ip:$ip";

            return [
                // Limits one stolen token hard
                Limit::perMinute(config('custom.rate_limits.api_per_token'))->by($tokenKey),

                // Prevents many tokens for one user hammering
                Limit::perMinute(config('custom.rate_limits.api_per_user'))->by($userKey),

                // Backstop only (kept higher to reduce NAT collateral damage)
                Limit::perMinute(config('custom.rate_limits.api_per_ip'))->by("ip:$ip"),
            ];
        });

        // Stricter limiter for resource-intensive endpoints
        RateLimiter::for('heavy', function (Request $request) {
            $user = $request->user();
            $tokenId = $user?->token()?->id;
            $ip = $request->ip();

            return [
                Limit::perMinute(config('custom.rate_limits.heavy'))->by($tokenId ? "token:$tokenId" : "ip:$ip"),
            ];
        });

        // Guesses against the shared HTTP Basic credential on the docs routes. Keyed by address
        // because the caller is a guest by definition -- there is no token or user to key by. Low
        // on purpose: a legitimate reader fetches the docs page and its document once and then
        // browses the rendered result locally, so this is generous for a human and useless for a
        // guesser.
        RateLimiter::for('api-docs', function (Request $request) {
            return Limit::perMinute(config('custom.rate_limits.api_docs'))->by('ip:' . $request->ip());
        });

        // The MCP endpoint (/api/mcp). Keyed by address because it runs BEFORE the client token is
        // checked: an unauthenticated caller has nothing else to key on, and counting first is what
        // caps token guessing.
        RateLimiter::for('mcp', function (Request $request) {
            return Limit::perMinute(config('custom.rate_limits.mcp'))->by('mcp:ip:' . $request->ip());
        });

        // The MCP OAuth endpoints (discovery, registration, authorize, token, consent), keyed by address:
        // unauthenticated by nature, and every registration writes a row. The sign-in form uses `sign-in`.
        RateLimiter::for('oauth', function (Request $request) {
            return Limit::perMinute(config('custom.rate_limits.oauth'))->by('oauth:ip:' . $request->ip());
        });

        // Define the gate permissions. checkPermissionTo denies an unseeded permission instead of throwing.
        foreach (UserPermission::cases() as $permission) {
            Gate::define($permission, function (User $user) use ($permission) {
                return $user->checkPermissionTo($permission, UserPermission::getApiGuardName());
            });
        }
    }

}
