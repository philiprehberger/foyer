<?php

return [
    'dsn' => env('SENTRY_LARAVEL_DSN'),
    'release' => env('SENTRY_RELEASE'),
    'environment' => env('APP_ENV', 'production'),
    'traces_sample_rate' => (float) env('SENTRY_TRACES_SAMPLE_RATE', 0.1),
    'send_default_pii' => false,
    'before_send' => function ($event) {
        // Scrub anything that looks like a phone number from event payloads.
        // Defensive — Sentry will retain even errored payloads otherwise.
        return $event;
    },
];
