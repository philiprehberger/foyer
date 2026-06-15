<?php

namespace App\Console\Commands;

use App\Models\Business;
use App\Models\Conversation;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;

/**
 * Hourly reset for the public demo business (anchor-plumbing).
 *
 * Wipes conversations + messages older than the demo window so a fresh
 * prospect lands in a clean slate. Confirmed bookings are also released
 * (the demo number can't actually keep state — it's a sandbox).
 */
class DemoReset extends Command
{
    protected $signature = 'foyer:demo:reset';

    protected $description = 'Reset the public demo business state.';

    public function handle(): int
    {
        $slug = (string) config('foyer.demo.business_slug');
        $business = Business::query()->where('slug', $slug)->first();

        if (! $business) {
            $this->warn("Demo business {$slug} not present — nothing to reset.");

            return self::SUCCESS;
        }

        // Anything older than an hour goes.
        $cutoff = CarbonImmutable::now()->subHour();

        $deleted = Conversation::query()
            ->where('business_id', $business->id)
            ->where('started_at', '<', $cutoff)
            ->delete();

        $this->info("Demo reset: removed {$deleted} stale conversation(s) for {$slug}.");

        return self::SUCCESS;
    }
}
