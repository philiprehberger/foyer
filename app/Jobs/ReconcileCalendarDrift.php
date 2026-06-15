<?php

namespace App\Jobs;

use App\Models\Booking;
use App\Models\Business;
use App\Models\SlotHold;
use App\Services\Calendar\CalendarConnector;
use Carbon\CarbonImmutable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Reconcile DB state with Google Calendar.
 *
 * Two trigger paths:
 *   1. `events.watch` push from Google → GoogleCalendarPushController dispatches us.
 *   2. CalendarSyncFallbackPoll cron → every 5min if the watch channel expired.
 *
 * For each business with a calendar id matching the resource, list events
 * for the next 14 days and compare to active holds + pending/confirmed
 * bookings. Out-of-band edits or deletes get reflected back into Foyer's
 * state and surfaced to the owner.
 */
class ReconcileCalendarDrift implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public readonly ?string $channelId = null,
        public readonly ?string $resourceId = null,
        public readonly ?string $resourceState = null,
    ) {
        $this->onQueue('calendar-sync');
    }

    public function handle(CalendarConnector $calendar): void
    {
        $businesses = Business::query()
            ->whereNotNull('google_calendar_id')
            ->get();

        foreach ($businesses as $business) {
            $events = $calendar->listBusy(
                $business->google_calendar_id,
                CarbonImmutable::now(),
                CarbonImmutable::now()->addDays(14),
            );

            $byEventId = [];
            foreach ($events as $e) {
                $byEventId[$e['event_id']] = $e;
            }

            // Confirmed bookings: if event id disappeared, mark canceled.
            Booking::query()
                ->where('business_id', $business->id)
                ->whereIn('status', [Booking::PENDING, Booking::CONFIRMED])
                ->whereNotNull('google_calendar_event_id')
                ->where('slot_start', '>=', CarbonImmutable::now())
                ->each(function (Booking $b) use ($byEventId) {
                    if (! isset($byEventId[$b->google_calendar_event_id])) {
                        $b->forceFill(['status' => Booking::CANCELED])->save();
                    }
                });

            // Active holds: same check.
            SlotHold::query()
                ->where('business_id', $business->id)
                ->where('status', SlotHold::ACTIVE)
                ->whereNotNull('google_calendar_event_id')
                ->each(function (SlotHold $h) use ($byEventId) {
                    if (! isset($byEventId[$h->google_calendar_event_id])) {
                        $h->forceFill([
                            'status' => SlotHold::RELEASED,
                            'released_at' => CarbonImmutable::now(),
                        ])->save();
                    }
                });
        }
    }
}
