<?php

namespace App\Providers;

use App\Constants\GateAbilityConstant;
use App\Constants\RoleConstant;
use App\Models\User;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Gate;
use Laravel\Passport\Passport;

class AuthServiceProvider extends ServiceProvider
{

    /**
     * The model to policy mappings for the application.
     *
     * @var array<class-string, class-string>
     */
    protected $policies = [
        // 'App\Models\Model' => 'App\Policies\ModelPolicy',
    ];

    /**
     * Register any authentication / authorization services.
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

        // Define gate (authorization)
        $this->defineUserGates();
    }

    private function defineUserGates(): void
    {
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
