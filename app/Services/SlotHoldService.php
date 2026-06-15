<?php

namespace App\Services;

use App\Models\Business;
use App\Models\Conversation;
use App\Models\SlotHold;
use App\Services\Calendar\CalendarConnector;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Uid\Ulid;

/**
 * Owns the slot-hold lifecycle: claim, confirm, release, expire.
 *
 * Claim is the load-bearing case. Two customers proposing the same slot
 * cannot both win — the EXCLUDE constraint on `slot_holds` raises a 23P01
 * exclusion_violation on the loser, which we translate into
 * SlotAlreadyHeldException for the agent to handle.
 *
 * Calendar event creation is part of the claim transaction in spirit: if
 * Calendar fails after the DB hold is open, we delete the DB hold inside
 * the same job so the customer can be told the slot is unavailable.
 */
class SlotHoldService
{
    public function __construct(private readonly CalendarConnector $calendar)
    {
    }

    /**
     * @throws SlotAlreadyHeldException
     */
    public function claim(
        Conversation $conversation,
        Business $business,
        CarbonInterface $start,
        CarbonInterface $end,
        string $serviceTypeKey,
        ?int $holdMinutes = null,
    ): SlotHold {
        $holdMinutes ??= (int) config('foyer.slot_hold_minutes', 15);

        $hold = null;

        try {
            DB::transaction(function () use ($conversation, $business, $start, $end, $serviceTypeKey, $holdMinutes, &$hold) {
                $hold = SlotHold::create([
                    'id' => (string) new Ulid,
                    'conversation_id' => $conversation->id,
                    'business_id' => $business->id,
                    'slot_start' => $start,
                    'slot_end' => $end,
                    'service_type_key' => $serviceTypeKey,
                    'status' => SlotHold::ACTIVE,
                    'proposed_at' => CarbonImmutable::now(),
                    'expires_at' => CarbonImmutable::now()->addMinutes($holdMinutes),
                ]);
            });
        } catch (QueryException $e) {
            // Postgres exclusion_violation = 23P01
            if (($e->errorInfo[0] ?? '') === '23P01' || str_contains($e->getMessage(), 'slot_holds_no_overlap')) {
                throw new SlotAlreadyHeldException;
            }

            throw $e;
        }

        if ($business->google_calendar_id) {
            try {
                $eventId = $this->calendar->createTentativeEvent(
                    $business->google_calendar_id,
                    "Foyer hold — pending",
                    $start,
                    $end,
                    ['conversation_id' => $conversation->id],
                );
                $hold->google_calendar_event_id = $eventId;
                $hold->save();
            } catch (\Throwable $e) {
                // Calendar failed — release the DB hold so the customer can be told.
                $hold->forceFill([
                    'status' => SlotHold::RELEASED,
                    'released_at' => CarbonImmutable::now(),
                ])->save();

                throw $e;
            }
        }

        return $hold;
    }

    public function confirm(SlotHold $hold, string $title = 'Foyer booking — confirmed'): void
    {
        $hold->forceFill([
            'status' => SlotHold::CONFIRMED,
        ])->save();

        if ($hold->google_calendar_event_id) {
            $business = $hold->business;
            if ($business?->google_calendar_id) {
                $this->calendar->confirmEvent(
                    $business->google_calendar_id,
                    $hold->google_calendar_event_id,
                    $title,
                );
            }
        }
    }

    public function release(SlotHold $hold): void
    {
        if ($hold->status !== SlotHold::ACTIVE) {
            return;
        }

        $hold->forceFill([
            'status' => SlotHold::RELEASED,
            'released_at' => CarbonImmutable::now(),
        ])->save();

        if ($hold->google_calendar_event_id) {
            $business = $hold->business;
            if ($business?->google_calendar_id) {
                try {
                    $this->calendar->deleteEvent(
                        $business->google_calendar_id,
                        $hold->google_calendar_event_id,
                    );
                } catch (\Throwable) {
                    // Best-effort — owner sees calendar-drift reconciliation later.
                }
            }
        }
    }

    public function expire(SlotHold $hold): void
    {
        if ($hold->status !== SlotHold::ACTIVE) {
            return;
        }

        $hold->forceFill([
            'status' => SlotHold::EXPIRED,
            'released_at' => CarbonImmutable::now(),
        ])->save();

        if ($hold->google_calendar_event_id) {
            $business = $hold->business;
            if ($business?->google_calendar_id) {
                try {
                    $this->calendar->deleteEvent(
                        $business->google_calendar_id,
                        $hold->google_calendar_event_id,
                    );
                } catch (\Throwable) {
                    // Reconciler will catch it.
                }
            }
        }
    }
}

class SlotAlreadyHeldException extends \RuntimeException
{
    public function __construct(string $message = 'The slot is already held by another customer.')
    {
        parent::__construct($message);
    }
}
