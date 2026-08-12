<?php

namespace App\Providers;

use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Laravel\Horizon\Horizon;
use Laravel\Horizon\HorizonApplicationServiceProvider;

class HorizonServiceProvider extends HorizonApplicationServiceProvider
{

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        parent::boot();

        // Horizon::routeSmsNotificationsTo('15556667777');
        // Horizon::routeMailNotificationsTo('example@example.com');
        // Horizon::routeSlackNotificationsTo('slack-webhook-url', '#channel');
    }

    /**
     * Register the Horizon gate.
     *
     * This gate determines who can access Horizon in non-local environments.
     *
     * The allowlist is environment-driven and EMPTY BY DEFAULT, which denies
     * everyone. That is the right default: the dashboard shows queued and failed
     * job payloads, and a failed job's payload routinely contains whatever the
     * job was given — user records, tokens, addresses. An allowlist that is empty
     * until somebody deliberately fills it cannot be left accidentally open.
     *
     * @return void
     */
    protected function gate(): void
    {
        Gate::define('viewHorizon', function (User $user): bool {
            $allowedEmails = config('custom.horizon_dashboard_emails');

            if (empty($allowedEmails)) {
                return false;
            }

            return in_array($user->email, $allowedEmails, true);
        });
    }

}
