<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\Business;
use App\Models\SlotHold;
use App\Services\Calendar\CalendarConnector;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * Find candidate slots inside the business's configured availability.
 *
 * Sources of unavailability, in order:
 *   1. Business hours and blocked dates (config)
 *   2. Lead-time bounds (min_lead_minutes, max_lead_days)
 *   3. Active slot_holds (DB) — covers in-flight proposals
 *   4. Live bookings (DB) — covers confirmed appointments
 *   5. Google Calendar busy events (Calendar) — covers owner-added events
 *
 * The DB EXCLUDE constraints (slot_holds_no_overlap, bookings_no_overlap)
 * are the load-bearing guard against double-booking; this search just
 * proposes candidates the agent can try to claim. The actual claim happens
 * in SlotHoldService::claim, which surfaces 23P01 violations to the agent
 * for fallback search.
 */
class SlotSearchService
{
    public function __construct(private readonly CalendarConnector $calendar)
    {
    }

    /**
     * @return array<int, array{start: CarbonInterface, end: CarbonInterface}>
     */
    public function search(
        Business $business,
        string $serviceTypeKey,
        int $maxResults = 5,
        ?CarbonInterface $earliest = null,
    ): array {
        $duration = $this->durationMinutesFor($business, $serviceTypeKey);
        $now = CarbonImmutable::now($business->timezone);
        $earliest = $earliest?->setTimezone($business->timezone) ?? $now;
        $earliest = $earliest->addMinutes($business->min_lead_minutes);

        $latest = $now->addDays($business->max_lead_days);

        $candidates = [];
        $cursor = $earliest->setSecond(0)->setMinute(0);
        $businessHours = $business->business_hours ?? [];

        while ($cursor <= $latest && count($candidates) < $maxResults) {
            $dow = strtolower($cursor->englishDayOfWeek);
            $windows = $businessHours[$dow] ?? [];

            foreach ($windows as $window) {
                $open = $this->parseHourMinute($cursor, $window['open'] ?? null);
                $close = $this->parseHourMinute($cursor, $window['close'] ?? null);
                if (! $open || ! $close) {
                    continue;
                }

                $slotStart = max($cursor, $open);
                while ($slotStart->copy()->addMinutes($duration) <= $close && count($candidates) < $maxResults) {
                    $slotEnd = $slotStart->copy()->addMinutes($duration);
                    if ($slotStart >= $earliest && $this->isFree($business, $slotStart, $slotEnd)) {
                        $candidates[] = ['start' => $slotStart, 'end' => $slotEnd];
                    }
                    $slotStart = $slotStart->copy()->addMinutes($duration);
                }
            }
            $cursor = $cursor->addDay()->startOfDay();
        }

        return $candidates;
    }

    private function isFree(Business $business, CarbonInterface $start, CarbonInterface $end): bool
    {
        $blocked = $business->blocked_dates ?? [];
        if (in_array($start->toDateString(), $blocked, true)) {
            return false;
        }

        $clashesActiveHolds = SlotHold::query()
            ->where('business_id', $business->id)
            ->where('status', SlotHold::ACTIVE)
            ->where('slot_start', '<', $end)
            ->where('slot_end', '>', $start)
            ->exists();
        if ($clashesActiveHolds) {
            return false;
        }

        $clashesBookings = Booking::query()
            ->where('business_id', $business->id)
            ->whereIn('status', [Booking::PENDING, Booking::CONFIRMED])
            ->where('slot_start', '<', $end)
            ->where('slot_end', '>', $start)
            ->exists();
        if ($clashesBookings) {
            return false;
        }

        if ($business->google_calendar_id) {
            foreach ($this->calendar->listBusy($business->google_calendar_id, $start, $end) as $busy) {
                if ($busy['start'] < $end && $busy['end'] > $start) {
                    return false;
                }
            }
        }

        return true;
    }

    private function durationMinutesFor(Business $business, string $serviceTypeKey): int
    {
        $st = $business->serviceTypes()->where('key', $serviceTypeKey)->first();

        return $st?->est_duration_min ?? 60;
    }

    private function parseHourMinute(CarbonInterface $on, ?string $hhmm): ?CarbonInterface
    {
        if (! $hhmm) {
            return null;
        }
        [$h, $m] = array_pad(explode(':', $hhmm), 2, '0');

        return $on->setTime((int) $h, (int) $m, 0);
    }
}
