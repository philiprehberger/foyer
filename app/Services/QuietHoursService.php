<?php

namespace App\Services;

use App\Models\Business;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * Quiet-hours enforcement for outbound SMS.
 *
 * Per the plan + FCC convention, default 21:00–08:00 customer-local. We
 * don't know the customer's local timezone, so we use the business's
 * timezone as a stand-in — documented as a limitation. Outbound messages
 * during quiet hours are scheduled to send at the next allowed window.
 */
class QuietHoursService
{
    public function isQuietNow(Business $business, ?CarbonInterface $at = null): bool
    {
        $now = ($at ?? CarbonImmutable::now())->setTimezone($business->timezone);
        $minutes = $now->hour * 60 + $now->minute;

        return $this->minuteIsInsideRange(
            $minutes,
            $this->toMinutes($business->quiet_hours_start),
            $this->toMinutes($business->quiet_hours_end),
        );
    }

    public function nextAllowedSend(Business $business, ?CarbonInterface $at = null): CarbonInterface
    {
        $now = ($at ?? CarbonImmutable::now())->setTimezone($business->timezone);

        if (! $this->isQuietNow($business, $now)) {
            return $now;
        }

        $endMinutes = $this->toMinutes($business->quiet_hours_end);
        $endHour = intdiv($endMinutes, 60);
        $endMinute = $endMinutes % 60;

        $candidate = $now->setTime($endHour, $endMinute, 0);

        // If the end-of-quiet time is earlier in the day than "now",
        // it lands tomorrow.
        if ($candidate <= $now) {
            $candidate = $candidate->addDay();
        }

        return $candidate;
    }

    private function toMinutes(string $hhmm): int
    {
        // Accepts "HH:MM" or "HH:MM:SS".
        $parts = explode(':', $hhmm);
        $h = (int) ($parts[0] ?? 0);
        $m = (int) ($parts[1] ?? 0);

        return $h * 60 + $m;
    }

    /**
     * The quiet-hours range spans midnight when end < start (e.g., 21:00–08:00).
     */
    private function minuteIsInsideRange(int $now, int $start, int $end): bool
    {
        if ($start === $end) {
            return false;
        }
        if ($start < $end) {
            return $now >= $start && $now < $end;
        }

        // Wrap-around midnight.
        return $now >= $start || $now < $end;
    }
}
