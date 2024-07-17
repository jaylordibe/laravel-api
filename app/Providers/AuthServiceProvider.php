<?php

namespace App\Providers;

use App\Constants\GateAbilityConstant;
use App\Constants\RoleConstant;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Laravel\Passport\Passport;

class AuthServiceProvider extends ServiceProvider
{

    /**
     * Register services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        Passport::tokensExpireIn(now()->addDays(15));
        Passport::refreshTokensExpireIn(now()->addDays(30));
        Passport::personalAccessTokensExpireIn(now()->addMonths(6));

        // Implicitly grant "SYSTEM_ADMIN" role all permission checks using can()
        Gate::before(function (User $user, $ability) {
            return $user->hasRole(RoleConstant::SYSTEM_ADMIN);
        });

        Gate::define(GateAbilityConstant::CAN_CREATE_USER, function (User $user) {
            return $user->can(GateAbilityConstant::CAN_CREATE_USER);
        });

        Gate::define(GateAbilityConstant::CAN_READ_USER, function (User $user) {
            return $user->can(GateAbilityConstant::CAN_READ_USER);
        });

        Gate::define(GateAbilityConstant::CAN_UPDATE_USER, function (User $user) {
            return $user->can(GateAbilityConstant::CAN_UPDATE_USER);
        });

        Gate::define(GateAbilityConstant::CAN_DELETE_USER, function (User $user) {
            return $user->can(GateAbilityConstant::CAN_DELETE_USER);
        });
    }

}
