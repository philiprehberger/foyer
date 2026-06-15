<?php

namespace App\Providers;

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
     * Any authenticated user with a business membership can view Horizon —
     * the Filament panel access gate (User::canAccessPanel) is the actual
     * check. Operator accounts are seeded via `foyer:seed-admin`.
     */
    protected function gate(): void
    {
        Gate::define('viewHorizon', fn ($user = null) => $user?->businesses()->exists() ?? false);
    }
}
