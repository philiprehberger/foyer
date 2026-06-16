<?php

namespace App\Console\Commands;

use App\Jobs\SendOutboundSms;
use App\Models\PhoneNumber;
use App\Models\SlotHold;
use App\Services\SlotHoldService;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;

/**
 * Releases active slot_holds whose 15-minute window expired.
 *
 * Schedule: every minute (routes/console.php). Per-business notification:
 * customer is texted "the slot is no longer available — want me to check
 * another time?" (quiet-hours-respecting; the SendOutboundSms job handles
 * the suppression + reschedule).
 */
class ExpireStaleSlotHolds extends Command
{
    protected $signature = 'foyer:slots:expire-stale';

    protected $description = 'Release slot_holds whose 15-minute window expired.';

    public function handle(SlotHoldService $hold): int
    {
        $now = CarbonImmutable::now();

        $expired = SlotHold::query()
            ->where('status', SlotHold::ACTIVE)
            ->where('expires_at', '<=', $now)
            ->with(['conversation', 'business'])
            ->limit(100)
            ->get();

        foreach ($expired as $h) {
            $hold->expire($h);

            $conv = $h->conversation;
            if (! $conv?->customer_phone_e164) {
                continue;
            }
            $twilio = PhoneNumber::query()->where('business_id', $h->business_id)->first();
            if (! $twilio) {
                continue;
            }

            SendOutboundSms::dispatch(
                $conv->id,
                $conv->customer_phone_e164,
                $twilio->number_e164,
                'That slot is no longer available — want me to check another time?',
            );
        }

        $this->info("Expired {$expired->count()} hold(s).");

        return self::SUCCESS;
    }
}
