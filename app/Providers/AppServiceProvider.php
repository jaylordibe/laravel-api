<?php

namespace App\Providers;

use App\Enums\UserPermission;
use App\Models\User;
use App\Utils\AuthUtil;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Laravel\Passport\Passport;

class AppServiceProvider extends ServiceProvider
{

    /**
     * Register any application services.
     *
     * Passport's own HTTP routes (`/oauth/*`) are not registered. Sign-in issues tokens in-process
     * through `createToken()`, so no client calls them, and they are attack surface only: the browser
     * authorization and device-code flows need a session-login guard this API does not have. Must run
     * in register(), before PassportServiceProvider boots and loads its routes. A fork that adopts an
     * OAuth grant registers the routes it needs deliberately rather than inheriting all of them.
     */
    public function register(): void
    {
        Passport::ignoreRoutes();
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Disable the wrapping of the outermost resource
        JsonResource::withoutWrapping();

        // OAuth grant access and refresh tokens: INERT while the /oauth/* routes are not registered (see
        // register()), so they do not affect sign-in. Kept rather than deleted: without them Passport defaults
        // both to one year, which a fork re-enabling a grant would inherit silently.
        Passport::tokensExpireIn(now()->addHours(8));
        Passport::refreshTokensExpireIn(now()->addDays(30));
        // Personal access tokens: what sign-in issues through createToken(), so this IS the session lifetime.
        // Throws on an invalid configured value rather than falling back (see AuthUtil).
        Passport::personalAccessTokensExpireIn(AuthUtil::personalAccessTokenLifetime());

        // Public limiter for unauthenticated endpoints
        RateLimiter::for('public', function (Request $request) {
            return Limit::perMinute(config('custom.rate_limits.public'))->by('ip:' . $request->ip());
        });

        // Very strict limiter for highly sensitive endpoints
        RateLimiter::for('sensitive', function (Request $request) {
            return Limit::perMinute(config('custom.rate_limits.sensitive'))->by('ip:' . $request->ip());
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

        // Define the gate permissions
        foreach (UserPermission::cases() as $permission) {
            Gate::define($permission, function (User $user) use ($permission) {
                return $user->hasPermissionTo($permission, UserPermission::getApiGuardName());
            });
        }
    }

}
