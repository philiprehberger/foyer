<?php

use App\Console\Commands\ExpireStaleSlotHolds;
use App\Console\Commands\CalendarSyncFallbackPoll;
use App\Console\Commands\DemoReset;
use Illuminate\Support\Facades\Schedule;

// Slot-hold cleanup — every 60s. Releases expired holds + deletes Calendar
// tentative event + notifies customer (quiet-hours-aware).
Schedule::command(ExpireStaleSlotHolds::class)
    ->everyMinute()
    ->withoutOverlapping()
    ->onOneServer();

// Calendar drift fallback — every 5 minutes if the watch channel expired.
Schedule::command(CalendarSyncFallbackPoll::class)
    ->everyFiveMinutes()
    ->withoutOverlapping()
    ->onOneServer();

// Hourly demo reset for the public demo business.
Schedule::command(DemoReset::class)
    ->hourly()
    ->withoutOverlapping()
    ->onOneServer();
