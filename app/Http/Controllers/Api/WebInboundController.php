<?php

namespace App\Http\Controllers\Api;

use App\Jobs\DispatchAgentTurn;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\WebSession;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Uid\Ulid;

/**
 * Inbound message from the embedded web widget.
 *
 * Auth: short-lived session token (bound to business_id + IP) in the
 * `Authorization: Bearer` header. Idempotent on `widget_message_id` (UUIDv7).
 *
 * Cross-channel resume: until the session has been phone-verified via OTP,
 * the conversation is web-only — phone-number-match alone is a spoofing
 * vector. See WebSessionController::verifyPhone.
 */
class WebInboundController
{
    public function __invoke(Request $request)
    {
        $token = $this->extractBearer($request);
        $session = $this->resolveSession($token);

        if (! $session) {
            return response()->json([
                'type' => 'urn:foyer:problem:web-session-invalid',
                'title' => 'Web session token is missing or invalid.',
                'status' => 401,
            ], 401, ['Content-Type' => 'application/problem+json']);
        }

        $data = $request->validate([
            'widget_message_id' => 'required|string|max:64',
            'text' => 'nullable|string|max:4000',
            'attachments' => 'array',
        ]);

        // Resolve / open conversation. Unverified sessions get their own conversation row.
        $conversation = $this->resolveOrOpenConversation($session);

        if ($conversation->business->isKilled()) {
            return response()->json([
                'type' => 'urn:foyer:problem:kill-switch-active',
                'title' => 'This business is temporarily handling requests manually.',
                'status' => 503,
            ], 503, ['Content-Type' => 'application/problem+json']);
        }

        $messageId = (string) new Ulid;
        $inserted = $this->insertIfNew([
            'id' => $messageId,
            'conversation_id' => $conversation->id,
            'external_id' => 'web:'.$data['widget_message_id'],
            'role' => Message::ROLE_CUSTOMER,
            'text' => $data['text'] ?? null,
            'attachments' => $data['attachments'] ?? [],
            'created_at' => CarbonImmutable::now(),
        ]);

        if ($inserted) {
            $conversation->forceFill(['last_message_at' => CarbonImmutable::now()])->save();
            DispatchAgentTurn::dispatch($conversation->id, $messageId);
        }

        return response()->json([
            'message_id' => $messageId,
            'accepted' => $inserted,
        ], 202);
    }

    private function extractBearer(Request $request): string
    {
        $auth = (string) $request->header('Authorization', '');
        if (str_starts_with($auth, 'Bearer ')) {
            return substr($auth, 7);
        }

        return '';
    }

    private function resolveSession(string $token): ?WebSession
    {
        if ($token === '') {
            return null;
        }

        $hash = hash('sha256', $token);
        $session = WebSession::query()->where('token_hash', $hash)->first();

        if (! $session || $session->expires_at->isPast()) {
            return null;
        }

        return $session;
    }

    private function resolveOrOpenConversation(WebSession $session): Conversation
    {
        // Phone-verified sessions resume the most-recent SMS or hybrid conversation
        // for that (business, phone) pair. Unverified sessions get a separate web row.
        if ($session->phone_verified_at && $session->customer_phone_e164) {
            $existing = Conversation::query()
                ->where('business_id', $session->business_id)
                ->where('customer_phone_e164', $session->customer_phone_e164)
                ->whereNull('completed_at')
                ->whereNull('abandoned_at')
                ->orderByDesc('started_at')
                ->first();

            if ($existing) {
                $existing->forceFill(['channel' => 'hybrid'])->save();

                return $existing;
            }
        }

        $existing = Conversation::query()
            ->where('business_id', $session->business_id)
            ->where('web_session_id', $session->id)
            ->whereNull('completed_at')
            ->whereNull('abandoned_at')
            ->first();

        if ($existing) {
            return $existing;
        }

        return Conversation::create([
            'business_id' => $session->business_id,
            'customer_phone_e164' => $session->customer_phone_e164,
            'web_session_id' => $session->id,
            'phone_verified_at' => $session->phone_verified_at,
            'channel' => 'web',
            'state' => [],
            'started_at' => CarbonImmutable::now(),
        ]);
    }

    private function insertIfNew(array $row): bool
    {
        try {
            // jsonb columns need explicit JSON encoding when bypassing Eloquent.
            if (isset($row['attachments']) && is_array($row['attachments'])) {
                $row['attachments'] = json_encode($row['attachments']);
            }
            if (isset($row['intent']) && is_array($row['intent'])) {
                $row['intent'] = json_encode($row['intent']);
            }

            DB::table('messages')->insert($row);

            return true;
        } catch (QueryException $e) {
            if (($e->errorInfo[0] ?? '') === '23505') {
                return false;
            }

            throw $e;
        }
    }
}
