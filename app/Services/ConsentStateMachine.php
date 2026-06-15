<?php

namespace App\Services;

use App\Models\ConsentChange;
use App\Models\ConsentState;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Twilio STOP/START/HELP first-responder logic.
 *
 * Per the plan: consent state is keyed on `(customer_phone_e164,
 * twilio_number_e164)` — STOP on one number does not STOP another. STOP wins,
 * no override (i.e., a STOP'd number ignores agent messages and cannot
 * resume via the web widget either; the widget enforces this on its side).
 */
class ConsentStateMachine
{
    public const KEYWORDS_STOP = ['STOP', 'STOPALL', 'UNSUBSCRIBE', 'CANCEL', 'END', 'QUIT'];
    public const KEYWORDS_START = ['START', 'YES', 'UNSTOP'];
    public const KEYWORDS_HELP = ['HELP', 'INFO'];

    public static function classify(string $body): ?string
    {
        $normalized = strtoupper(trim($body));

        if (in_array($normalized, self::KEYWORDS_STOP, true)) {
            return 'stop';
        }
        if (in_array($normalized, self::KEYWORDS_START, true)) {
            return 'start';
        }
        if (in_array($normalized, self::KEYWORDS_HELP, true)) {
            return 'help';
        }

        return null;
    }

    public static function isStopped(string $customerE164, string $twilioE164): bool
    {
        return ConsentState::isStopped($customerE164, $twilioE164);
    }

    public static function applyStop(string $customerE164, string $twilioE164, ?string $sourceMessageId = null): void
    {
        DB::transaction(function () use ($customerE164, $twilioE164, $sourceMessageId) {
            $prior = ConsentState::query()
                ->where('customer_phone_e164', $customerE164)
                ->where('twilio_number_e164', $twilioE164)
                ->lockForUpdate()
                ->first();

            $from = $prior?->state ?? ConsentState::SUBSCRIBED;

            ConsentState::query()->updateOrCreate(
                [
                    'customer_phone_e164' => $customerE164,
                    'twilio_number_e164' => $twilioE164,
                ],
                [
                    'state' => ConsentState::STOPPED,
                    'last_change_at' => CarbonImmutable::now(),
                ],
            );

            ConsentChange::create([
                'customer_phone_e164' => $customerE164,
                'twilio_number_e164' => $twilioE164,
                'from_state' => $from,
                'to_state' => ConsentState::STOPPED,
                'source_message_id' => $sourceMessageId,
                'created_at' => CarbonImmutable::now(),
            ]);
        });
    }

    public static function applyStart(string $customerE164, string $twilioE164, ?string $sourceMessageId = null): void
    {
        DB::transaction(function () use ($customerE164, $twilioE164, $sourceMessageId) {
            $prior = ConsentState::query()
                ->where('customer_phone_e164', $customerE164)
                ->where('twilio_number_e164', $twilioE164)
                ->lockForUpdate()
                ->first();

            $from = $prior?->state ?? ConsentState::SUBSCRIBED;

            ConsentState::query()->updateOrCreate(
                [
                    'customer_phone_e164' => $customerE164,
                    'twilio_number_e164' => $twilioE164,
                ],
                [
                    'state' => ConsentState::SUBSCRIBED,
                    'last_change_at' => CarbonImmutable::now(),
                ],
            );

            ConsentChange::create([
                'customer_phone_e164' => $customerE164,
                'twilio_number_e164' => $twilioE164,
                'from_state' => $from,
                'to_state' => ConsentState::SUBSCRIBED,
                'source_message_id' => $sourceMessageId,
                'created_at' => CarbonImmutable::now(),
            ]);
        });
    }
}
