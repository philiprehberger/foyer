<?php

namespace App\Http\Controllers\Api;

use App\Jobs\SendOutboundSms;
use App\Models\AuditLog;
use App\Models\Booking;
use App\Models\PhoneNumber;
use App\Models\SlotHold;
use App\Services\Calendar\CalendarConnector;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class BookingsController
{
    public function index(Request $request): JsonResponse
    {
        $businessIds = $request->user()->businesses()->pluck('businesses.id')->all();
        $query = Booking::query()->whereIn('business_id', $businessIds);

        if ($request->filled('status')) {
            $query->where('status', $request->query('status'));
        }
        if ($request->filled('business_id')) {
            $bid = (string) $request->query('business_id');
            if (! in_array($bid, $businessIds, true)) {
                abort(403);
            }
            $query->where('business_id', $bid);
        }

        $rows = $query->orderByDesc('slot_start')->paginate(50);

        return response()->json([
            'data' => $rows->items(),
            'page' => $rows->currentPage(),
            'per_page' => $rows->perPage(),
            'total' => $rows->total(),
        ]);
    }

    public function show(Request $request, string $bookingId): JsonResponse
    {
        $booking = Booking::query()->with('conversation.messages')->findOrFail($bookingId);
        if (! $request->user()->ownsBusiness($booking->business_id)) {
            abort(403);
        }

        return response()->json([
            'booking' => $booking,
            'transcript' => $booking->conversation?->messages,
        ]);
    }

    public function confirm(Request $request, string $bookingId, CalendarConnector $calendar): JsonResponse
    {
        $booking = Booking::query()->findOrFail($bookingId);
        if (! $request->user()->ownsBusiness($booking->business_id)) {
            abort(403);
        }

        DB::transaction(function () use ($booking, $request, $calendar) {
            if ($booking->status === Booking::CONFIRMED) {
                return; // idempotent: already done
            }

            $booking->forceFill([
                'status' => Booking::CONFIRMED,
                'confirmed_by' => $request->user()->id,
                'confirmed_at' => CarbonImmutable::now(),
            ])->save();

            $hold = SlotHold::query()
                ->where('conversation_id', $booking->conversation_id)
                ->where('status', SlotHold::ACTIVE)
                ->first();
            if ($hold) {
                $hold->forceFill(['status' => SlotHold::CONFIRMED])->save();
            }

            $business = $booking->business;
            if ($business?->google_calendar_id && $booking->google_calendar_event_id) {
                $calendar->confirmEvent(
                    $business->google_calendar_id,
                    $booking->google_calendar_event_id,
                    "Booking — {$business->name}",
                );
            }

            AuditLog::create([
                'actor_type' => 'user',
                'actor_id' => (string) $request->user()->id,
                'business_id' => $booking->business_id,
                'event' => 'booking.confirmed',
                'payload' => ['booking_id' => $booking->id],
                'ip' => $request->ip(),
                'created_at' => CarbonImmutable::now(),
            ]);
        });

        $this->notifyCustomer($booking, "Confirmed — see you {$booking->slot_start->toDayDateTimeString()}. Reply STOP to opt out.");

        return response()->json(['booking' => $booking->fresh()]);
    }

    public function reject(Request $request, string $bookingId, CalendarConnector $calendar): JsonResponse
    {
        $booking = Booking::query()->findOrFail($bookingId);
        if (! $request->user()->ownsBusiness($booking->business_id)) {
            abort(403);
        }

        $data = $request->validate([
            'reason' => 'nullable|string|max:500',
        ]);

        DB::transaction(function () use ($booking, $request, $calendar, $data) {
            if (in_array($booking->status, [Booking::CANCELED], true)) {
                return; // idempotent
            }

            $booking->forceFill(['status' => Booking::CANCELED])->save();

            $hold = SlotHold::query()
                ->where('conversation_id', $booking->conversation_id)
                ->where('status', SlotHold::ACTIVE)
                ->first();
            if ($hold) {
                $hold->forceFill([
                    'status' => SlotHold::RELEASED,
                    'released_at' => CarbonImmutable::now(),
                ])->save();
            }

            $business = $booking->business;
            if ($business?->google_calendar_id && $booking->google_calendar_event_id) {
                try {
                    $calendar->deleteEvent(
                        $business->google_calendar_id,
                        $booking->google_calendar_event_id,
                    );
                } catch (\Throwable) {
                    // best-effort
                }
            }

            AuditLog::create([
                'actor_type' => 'user',
                'actor_id' => (string) $request->user()->id,
                'business_id' => $booking->business_id,
                'event' => 'booking.rejected',
                'payload' => ['booking_id' => $booking->id, 'reason' => $data['reason'] ?? null],
                'ip' => $request->ip(),
                'created_at' => CarbonImmutable::now(),
            ]);
        });

        $this->notifyCustomer($booking, "We can't make that work — I can check alternative times if you'd like.");

        return response()->json(['booking' => $booking->fresh()]);
    }

    private function notifyCustomer(Booking $booking, string $body): void
    {
        if (! $booking->customer_phone_e164) {
            return;
        }
        $twilio = PhoneNumber::query()->where('business_id', $booking->business_id)->first();
        if (! $twilio) {
            return;
        }
        SendOutboundSms::dispatch(
            $booking->conversation_id,
            $booking->customer_phone_e164,
            $twilio->number_e164,
            $body,
        );
    }
}
