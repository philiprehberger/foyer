<?php

namespace App\Providers;

use App\Models\Business;
use App\Policies\BusinessPolicy;
use App\Services\Calendar\CalendarConnector;
use App\Services\Calendar\FakeCalendarConnector;
use App\Services\Calendar\GoogleCalendarConnector;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // CalendarConnector binding — env-aware so tests resolve the Fake.
        $this->app->singleton(CalendarConnector::class, function ($app) {
            if ($app->environment('testing') || $app->runningUnitTests()) {
                return new FakeCalendarConnector;
            }

            $refresh = (string) request()?->attributes?->get('google_refresh_token', '');
            if ($refresh !== '') {
                return GoogleCalendarConnector::forRefreshToken($refresh);
            }

            return new FakeCalendarConnector;
        });
    }

    public function boot(): void
    {
        Gate::policy(Business::class, BusinessPolicy::class);
    }
}
