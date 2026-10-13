<?php

namespace App\Providers;

use App\Enums\UserPermission;
use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Support\ServiceProvider;
use Laravel\Passport\Passport;

class AppServiceProvider extends ServiceProvider
{

    /**
     * Register any application services.
     *
     * Passport's `/oauth/*` routes are not registered: sign-in issues tokens with `createToken()`.
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

        // OAuth grant tokens (unused while /oauth/* routes are off).
        Passport::tokensExpireIn(now()->addHours(8));
        Passport::refreshTokensExpireIn(now()->addDays(30));
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

        // Define the gate permissions. checkPermissionTo denies an unseeded permission instead of throwing.
        foreach (UserPermission::cases() as $permission) {
            Gate::define($permission, function (User $user) use ($permission) {
                return $user->checkPermissionTo($permission, UserPermission::getApiGuardName());
            });
        }
    }

}
