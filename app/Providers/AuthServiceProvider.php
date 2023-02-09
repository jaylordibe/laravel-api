<?php

namespace App\Providers;

use App\Constants\GateAbilityConstant;
use App\Constants\UserRoleConstant;
use App\Models\User;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Gate;
use Laravel\Passport\Passport;

class AuthServiceProvider extends ServiceProvider {

    /**
     * The policy mappings for the application.
     *
     * @var array<class-string, class-string>
     */
    protected $policies = [
        // 'App\Models\Model' => 'App\Policies\ModelPolicy',
    ];

    /**
     * Register any authentication / authorization services.
     *
     * @return void
     */
    public function boot() {
        $this->registerPolicies();

        if (!$this->app->routesAreCached()) {
            Passport::routes();
            Passport::tokensExpireIn(now()->addDays(15));
            Passport::refreshTokensExpireIn(now()->addDays(30));
            Passport::personalAccessTokensExpireIn(now()->addMonths(6));
        }

        // Define gate (authorization)
        Gate::define(GateAbilityConstant::SYSTEM_ADMIN, function (User $user) {
            return $user->toDto()->getRole() === UserRoleConstant::SYSTEM_ADMIN;
        });

        Gate::define(GateAbilityConstant::ADMIN, function (User $user) {
            return $user->toDto()->getRole() === UserRoleConstant::ADMIN;
        });

        Gate::define(GateAbilityConstant::MEMBER, function (User $user) {
            return $user->toDto()->getRole() === UserRoleConstant::MEMBER;
        });

        Gate::define(GateAbilityConstant::SYSTEM_ADMIN_OR_ADMIN, function (User $user) {
            $allowedRoles = [UserRoleConstant::SYSTEM_ADMIN, UserRoleConstant::ADMIN];
            return in_array($user->toDto()->getRole(), $allowedRoles, true);
        });
    }
}
