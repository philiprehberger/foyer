<?php

namespace App\Jobs;

use App\Models\Business;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\MessageDelivery;
use App\Services\QuietHoursService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Symfony\Component\Uid\Ulid;
use Twilio\Rest\Client as TwilioClient;

/**
 * Outbound SMS dispatch.
 *
 * Pre-flight checks: business kill switch, customer consent state,
 * quiet hours. If quiet, the job is re-queued with a delay until the next
 * allowed send time.
 *
 * The Twilio call is the side effect; the `messages` row is created BEFORE
 * the call so the SID can be backfilled by the status-callback path and any
 * retry inserts a new attempt under the same conversation.
 */
class SendOutboundSms implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 5;
    public int $backoff = 30;

    public function __construct(
        public readonly string $conversationId,
        public readonly string $toE164,
        public readonly string $fromE164,
        public readonly string $body,
        public readonly ?string $messageId = null,
    ) {
        $this->onQueue('twilio-outbound');
    }

    public function handle(QuietHoursService $quietHours, ?TwilioClient $twilio = null): void
    {
        $conversation = Conversation::query()->findOrFail($this->conversationId);
        $business = Business::query()->findOrFail($conversation->business_id);

        if ($business->isKilled()) {
            $this->delete();

            return;
        }

        // STOP wins, no override.
        if (\App\Services\ConsentStateMachine::isStopped($this->toE164, $this->fromE164)) {
            $this->delete();

            return;
        }

        // Quiet-hours suppression with scheduled-send.
        if ($quietHours->isQuietNow($business)) {
            $delay = $quietHours->nextAllowedSend($business)->diffInSeconds(now());
            $this->release(max(1, (int) $delay));

            return;
        }

        $messageId = $this->messageId ?? (string) new Ulid;

        $msg = Message::query()->updateOrCreate(
            ['id' => $messageId],
            [
                'conversation_id' => $this->conversationId,
                'external_id' => 'outbound:'.$messageId,
                'role' => Message::ROLE_AGENT,
                'text' => $this->body,
                'phase' => $conversation->currentPhase(),
                'created_at' => now(),
            ],
        );

        MessageDelivery::query()->updateOrCreate(
            ['message_id' => $msg->id],
            ['status' => 'queued'],
        );

        if ($twilio === null) {
            $sid = (string) config('services.twilio.account_sid');
            $token = (string) config('services.twilio.auth_token');
            if ($sid === '' || $token === '') {
                // Local dev / tests with no Twilio credentials — leave the message
                // as "queued" and let the test inspect it. Production paths set both.
                return;
            }
            $twilio = new TwilioClient($sid, $token);
        }

        $resp = $twilio->messages->create($this->toE164, [
            'from' => $this->fromE164,
            'body' => $this->body,
            'statusCallback' => (string) config('services.twilio.status_callback_url') ?: null,
        ]);

        MessageDelivery::query()->where('message_id', $msg->id)->update([
            'twilio_sid' => $resp->sid,
            'status' => $resp->status ?? 'sent',
        ]);
    }
}
