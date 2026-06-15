<?php

namespace App\Console\Commands;

use App\Jobs\ReconcileCalendarDrift;
use Illuminate\Console\Command;

class CalendarSyncFallbackPoll extends Command
{
    protected $signature = 'foyer:calendar:fallback-poll';

    protected $description = 'Fallback Google Calendar drift check if the watch channel expired.';

    public function handle(): int
    {
        ReconcileCalendarDrift::dispatch();

        return self::SUCCESS;
    }
}
