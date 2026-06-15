<?php

namespace App\Http\Controllers\Api;

use App\Jobs\DispatchAgentTurn;
use App\Jobs\SendOutboundSms;
use App\Models\Business;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\PhoneNumber;
use App\Services\ConsentStateMachine;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Uid\Ulid;

/**
 * Inbound Twilio SMS webhook — fast-ack within 500ms.
 *
 * Order:
 *   1. Signature already validated by twilio.sig middleware.
 *   2. Resolve the destination business via phone_numbers.
 *   3. STOP/START/HELP first responder — never dispatches the agent.
 *   4. Idempotent insert on (external_id = MessageSid).
 *   5. Dispatch AgentTurn to Redis. Return 200.
 *
 * Twilio always retries on 5xx; tail latency matters for delivery rates, so
 * the heavy work (LLM) is fully out-of-band.
 */
class SmsInboundController
{
    public function __invoke(Request $request)
    {
        $sid = (string) $request->input('MessageSid', '');
        $from = (string) $request->input('From', '');
        $to = (string) $request->input('To', '');
        $body = trim((string) $request->input('Body', ''));
        $numMedia = (int) $request->input('NumMedia', 0);

        if ($sid === '' || $from === '' || $to === '') {
            return response()->json([
                'type' => 'urn:foyer:problem:invalid-twilio-payload',
                'title' => 'Missing required Twilio fields.',
                'status' => 400,
            ], 400, ['Content-Type' => 'application/problem+json']);
        }

        $phone = PhoneNumber::query()->where('number_e164', $to)->first();

        if (! $phone) {
            // Unknown destination — log and 200 (so Twilio doesn't retry indefinitely).
            return response('<?xml version="1.0" encoding="UTF-8"?><Response/>', 200, [
                'Content-Type' => 'text/xml',
            ]);
        }

        $business = Business::query()->findOrFail($phone->business_id);

        // STOP / START / HELP — first responder.
        $keyword = ConsentStateMachine::classify($body);
        if ($keyword !== null) {
            return $this->handleKeyword($keyword, $from, $to, $business);
        }

        if (ConsentStateMachine::isStopped($from, $to)) {
            return response('<?xml version="1.0" encoding="UTF-8"?><Response/>', 200, [
                'Content-Type' => 'text/xml',
            ]);
        }

        if ($business->isKilled()) {
            // Kill-switch mode — static reply, no agent dispatch.
            $this->dispatchOutbound($business, $from, $to, $this->killSwitchMessage());

            return response('<?xml version="1.0" encoding="UTF-8"?><Response/>', 200, [
                'Content-Type' => 'text/xml',
            ]);
        }

        // Resolve or open the conversation. Multi-tenant safe: scoped by business + customer phone.
        $conversation = $this->resolveOrOpenConversation($business->id, $from);

        // Idempotent insert on (external_id) — duplicate webhook = 200 without dispatch.
        $messageId = (string) new Ulid;
        $inserted = $this->insertIfNew([
            'id' => $messageId,
            'conversation_id' => $conversation->id,
            'external_id' => $sid,
            'role' => Message::ROLE_CUSTOMER,
            'text' => $body,
            'attachments' => $this->extractAttachments($request, $numMedia),
            'created_at' => CarbonImmutable::now(),
        ]);

        if ($inserted) {
            $conversation->forceFill(['last_message_at' => CarbonImmutable::now()])->save();
            DispatchAgentTurn::dispatch($conversation->id, $messageId);
        }

        return response('<?xml version="1.0" encoding="UTF-8"?><Response/>', 200, [
            'Content-Type' => 'text/xml',
        ]);
    }

    private function resolveOrOpenConversation(string $businessId, string $customerE164): Conversation
    {
        $existing = Conversation::query()
            ->where('business_id', $businessId)
            ->where('customer_phone_e164', $customerE164)
            ->whereNull('completed_at')
            ->whereNull('abandoned_at')
            ->orderByDesc('started_at')
            ->first();

        if ($existing) {
            return $existing;
        }

        return Conversation::create([
            'business_id' => $businessId,
            'customer_phone_e164' => $customerE164,
            'channel' => 'sms',
            'state' => [],
            'started_at' => CarbonImmutable::now(),
        ]);
    }

    /**
     * Insert with `ON CONFLICT (external_id) DO NOTHING`-equivalent semantics
     * by catching the unique-constraint violation. Returns true if a new row
     * was inserted; false if the row already existed (Twilio retry).
     */
    private function insertIfNew(array $row): bool
    {
        try {
            DB::table('messages')->insert($row);

            return true;
        } catch (QueryException $e) {
            $code = $e->errorInfo[0] ?? '';

            // 23505 = Postgres unique_violation
            if ($code === '23505') {
                return false;
            }

            throw $e;
        }
    }

    /**
     * @return array<int, array{type: string, url: string, content_type: string}>
     */
    private function extractAttachments(Request $request, int $numMedia): array
    {
        $items = [];
        for ($i = 0; $i < $numMedia; $i++) {
            $url = (string) $request->input("MediaUrl{$i}");
            $type = (string) $request->input("MediaContentType{$i}");
            if ($url === '') {
                continue;
            }
            $items[] = ['type' => 'mms', 'url' => $url, 'content_type' => $type];
        }

        return $items;
    }

    private function handleKeyword(string $keyword, string $from, string $to, Business $business)
    {
        $reply = match ($keyword) {
            'stop' => 'You will no longer receive SMS from this number. Reply START to resume.',
            'start' => 'You have re-subscribed to this number. Reply STOP to opt out at any time.',
            'help' => "{$business->name}: text us to book service. Standard message and data rates may apply. Reply STOP to opt out.",
            default => '',
        };

        if ($keyword === 'stop') {
            ConsentStateMachine::applyStop($from, $to);
        } elseif ($keyword === 'start') {
            ConsentStateMachine::applyStart($from, $to);
        }

        // STOP / START / HELP responses must always be sent — they bypass quiet
        // hours and the kill switch per Twilio policy. The SendOutboundSms job
        // respects consent, so for the STOP reply we send the Twilio-required
        // confirmation via the TwiML reply instead of the queue.
        $twiml = '<?xml version="1.0" encoding="UTF-8"?><Response>'
            .'<Message>'.htmlspecialchars($reply, ENT_XML1 | ENT_QUOTES, 'UTF-8').'</Message>'
            .'</Response>';

        return response($twiml, 200, ['Content-Type' => 'text/xml']);
    }

    private function dispatchOutbound(Business $business, string $to, string $from, string $body): void
    {
        $conversation = $this->resolveOrOpenConversation($business->id, $to);
        SendOutboundSms::dispatch($conversation->id, $to, $from, $body);
    }

    private function killSwitchMessage(): string
    {
        return "We're temporarily handling bookings manually. Someone will reach out shortly.";
    }
}
