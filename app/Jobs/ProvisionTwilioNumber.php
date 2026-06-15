<?php

namespace App\Jobs;

use App\Models\PhoneNumber;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Twilio\Rest\Client as TwilioClient;

/**
 * Async Twilio number provisioning.
 *
 * Phase 9 wires the real Twilio Search + Buy + 10DLC-attach flow. For
 * Phase 1-5 the job records "would-have-bought" state so the owner UX can
 * be demoed end-to-end against a stubbed Twilio. Production requires the
 * services.twilio.account_sid to be set and a real Twilio account.
 */
class ProvisionTwilioNumber implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public readonly string $jobId,
        public readonly string $businessId,
        public readonly ?string $areaCode = null,
    ) {
        $this->onQueue('twilio-outbound');
    }

    public function handle(?TwilioClient $twilio = null): void
    {
        Cache::put("foyer:jobs:{$this->jobId}", [
            'job_id' => $this->jobId,
            'status' => 'in_progress',
        ], now()->addHour());

        if (! config('services.twilio.account_sid')) {
            // Stub: pretend we bought a number.
            $fake = '+1'.($this->areaCode ?? '555').'5550100';
            $this->finalize($fake);

            return;
        }

        $twilio ??= new TwilioClient(
            (string) config('services.twilio.account_sid'),
            (string) config('services.twilio.auth_token'),
        );

        $available = $twilio->availablePhoneNumbers('US')
            ->local
            ->read([
                'areaCode' => $this->areaCode,
                'smsEnabled' => true,
                'mmsEnabled' => true,
            ], 1);

        if (empty($available)) {
            Cache::put("foyer:jobs:{$this->jobId}", [
                'job_id' => $this->jobId,
                'status' => 'failed',
                'reason' => 'no_numbers_available',
            ], now()->addHour());

            return;
        }

        $bought = $twilio->incomingPhoneNumbers->create([
            'phoneNumber' => $available[0]->phoneNumber,
        ]);

        $this->finalize($bought->phoneNumber);
    }

    private function finalize(string $e164): void
    {
        PhoneNumber::create([
            'business_id' => $this->businessId,
            'number_e164' => $e164,
            'provisioned_at' => now(),
            'status' => 'active',
        ]);

        Cache::put("foyer:jobs:{$this->jobId}", [
            'job_id' => $this->jobId,
            'status' => 'completed',
            'number_e164' => $e164,
        ], now()->addDay());
    }
}
