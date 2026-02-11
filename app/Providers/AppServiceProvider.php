<?php

namespace App\Providers;

use App\Enums\UserPermission;
use App\Models\User;
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
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Disable the wrapping of the outermost resource
        JsonResource::withoutWrapping();

        // Access tokens: used on every request
        Passport::tokensExpireIn(now()->addHours(8));
        // Refresh tokens: used to rotate access tokens
        Passport::refreshTokensExpireIn(now()->addDays(30));
        // Personal access tokens: used for long-lived tokens (e.g. for CLI or third-party integrations)
        Passport::personalAccessTokensExpireIn(now()->addDays(90));

        // Public limiter for unauthenticated endpoints
        RateLimiter::for('public', function (Request $request) {
            return Limit::perMinute(60)->by('ip:' . $request->ip());
        });

        // Very strict limiter for highly sensitive endpoints
        RateLimiter::for('sensitive', function (Request $request) {
            return Limit::perMinute(5)->by('ip:' . $request->ip());
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
                Limit::perMinute(60)->by($tokenKey),

                // Prevents many tokens for one user hammering
                Limit::perMinute(120)->by($userKey),

                // Backstop only (kept higher to reduce NAT collateral damage)
                Limit::perMinute(300)->by("ip:$ip"),
            ];
        });

        // Stricter limiter for resource-intensive endpoints
        RateLimiter::for('heavy', function (Request $request) {
            $user = $request->user();
            $tokenId = $user?->token()?->id;
            $ip = $request->ip();

            return [
                Limit::perMinute(10)->by($tokenId ? "token:$tokenId" : "ip:$ip"),
            ];
        });

        // Define the gate permissions
        foreach (UserPermission::cases() as $permission) {
            Gate::define($permission, function (User $user) use ($permission) {
                return $user->hasPermissionTo($permission, UserPermission::getApiGuardName());
            });
        }
    }

}
