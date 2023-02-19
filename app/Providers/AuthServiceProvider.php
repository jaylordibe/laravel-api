<?php

namespace App\Providers;

// use Illuminate\Support\Facades\Gate;
use App\Constants\GateAbilityConstant;
use App\Constants\UserRoleConstant;
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

        // Define gate (authorization)
        Gate::define(GateAbilityConstant::SYSTEM_ADMIN, function (User $user) {
            return $user->role === UserRoleConstant::SYSTEM_ADMIN;
        });

        Gate::define(GateAbilityConstant::ADMIN, function (User $user) {
            return $user->role === UserRoleConstant::ADMIN;
        });

        Gate::define(GateAbilityConstant::MEMBER, function (User $user) {
            return $user->role === UserRoleConstant::MEMBER;
        });

        Gate::define(GateAbilityConstant::SYSTEM_ADMIN_OR_ADMIN, function (User $user) {
            $allowedRoles = [UserRoleConstant::SYSTEM_ADMIN, UserRoleConstant::ADMIN];
            return in_array($user->role, $allowedRoles, true);
        });
    }

}
