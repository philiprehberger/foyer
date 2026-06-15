<?php

return [
    'internal_secret' => env('FOYER_INTERNAL_SECRET', ''),
    'internal_bind' => env('FOYER_INTERNAL_BIND', '127.0.0.1'),
    'agent_worker_url' => env('FOYER_AGENT_WORKER_URL', 'http://127.0.0.1:8800'),

    'slot_hold_minutes' => (int) env('FOYER_SLOT_HOLD_MINUTES', 15),
    'default_cost_ceiling_micros' => (int) env('FOYER_DEFAULT_COST_CEILING_MICROS', 500000),

    'photos' => [
        'bucket' => env('FOYER_PHOTOS_BUCKET', 'foyer-photos'),
        'region' => env('FOYER_PHOTOS_REGION', 'us-west-2'),
        'max_bytes_mms' => 5 * 1024 * 1024,
        'max_bytes_web' => 10 * 1024 * 1024,
        'allowed_mime' => ['image/jpeg', 'image/png', 'image/heic', 'image/webp'],
    ],

    'quiet_hours' => [
        'default_start' => env('FOYER_DEFAULT_QUIET_START', '21:00'),
        'default_end' => env('FOYER_DEFAULT_QUIET_END', '08:00'),
    ],

    'demo' => [
        'business_slug' => env('FOYER_DEMO_BUSINESS_SLUG', 'anchor-plumbing'),
        'daily_limit_per_number' => (int) env('FOYER_DEMO_DAILY_LIMIT_PER_NUMBER', 3),
        'twilio_daily_spend_ceiling' => (int) env('FOYER_DEMO_TWILIO_DAILY_SPEND_CEILING', 10000),
    ],

    'llm' => [
        'provider' => env('FOYER_LLM_PROVIDER', 'mock'),
        'model' => env('FOYER_LLM_MODEL', 'claude-sonnet-4-6'),
    ],
];
