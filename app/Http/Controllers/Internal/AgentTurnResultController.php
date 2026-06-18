<?php

namespace App\Http\Controllers\Internal;

use App\Jobs\SendOutboundSms;
use App\Models\Conversation;
use App\Models\LlmCostDaily;
use App\Models\Message;
use App\Models\OutOfScopeLog;
use App\Models\PhoneNumber;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Uid\Ulid;

/**
 * POST /_internal/conversations/{conversation}/turn-result
 *
 * Receives the FastAPI agent worker's structured output for one turn:
 * next phase, reply text, intent calls, token + cost. Idempotent on the
 * worker-supplied message_id.
 */
class AgentTurnResultController
{
    public function __invoke(Request $request, string $conversationId): JsonResponse
    {
        $data = $request->validate([
            // The worker doesn't currently mint a per-turn ULID; Laravel
            // generates one server-side if absent. Kept nullable (not absent)
            // so a worker that does start sending one in future stays valid.
            'message_id' => 'nullable|string|size:26',
            'next_phase' => 'required|string|max:64',
            'reply_text' => 'nullable|string|max:4000',
            'intents' => 'array',
            'confidence' => 'nullable|numeric|min:0|max:1',
            'tokens_in' => 'integer|min:0',
            'tokens_out' => 'integer|min:0',
            'cost_micros' => 'integer|min:0',
            'model' => 'nullable|string|max:128',
            'out_of_scope_reason' => 'nullable|string|max:128',
            'out_of_scope_text' => 'nullable|string|max:4000',
        ]);

        $conversation = Conversation::query()->findOrFail($conversationId);

        \Illuminate\Support\Facades\Log::info('agent.turn-result.posted', [
            'conversation_id' => $conversationId,
            'next_phase' => $data['next_phase'],
            'reply_text_snippet' => substr($data['reply_text'] ?? '', 0, 80),
            'model' => $data['model'] ?? null,
        ]);

        $messageId = $data['message_id'] ?? (string) new Ulid;

        DB::transaction(function () use ($data, $conversation, $messageId) {
            // Idempotent: insert if external_id doesn't yet exist. message_id
            // is worker-supplied when present (deterministic-retry-safe) or
            // server-generated when absent (still unique per request).
            $external = 'agent:'.$messageId;
            $alreadyHave = Message::query()->where('external_id', $external)->exists();
            if ($alreadyHave) {
                return;
            }

            Message::create([
                'id' => (string) new Ulid,
                'conversation_id' => $conversation->id,
                'external_id' => $external,
                'role' => Message::ROLE_AGENT,
                'text' => $data['reply_text'] ?? null,
                'phase' => $data['next_phase'],
                'intent' => [
                    'intents' => $data['intents'] ?? [],
                    'confidence' => $data['confidence'] ?? null,
                ],
                'model' => $data['model'] ?? null,
                'tokens_in' => (int) ($data['tokens_in'] ?? 0),
                'tokens_out' => (int) ($data['tokens_out'] ?? 0),
                'cost_micros' => (int) ($data['cost_micros'] ?? 0),
                'created_at' => CarbonImmutable::now(),
            ]);

            $conversation->forceFill([
                'last_message_at' => CarbonImmutable::now(),
                'completed_at' => in_array($data['next_phase'], ['completed', 'abandon', 'out_of_scope', 'human_handoff'], true)
                    ? CarbonImmutable::now()
                    : $conversation->completed_at,
            ])->save();

            $this->bumpCostRollup($conversation->business_id, $data);

            if ($data['next_phase'] === 'out_of_scope') {
                OutOfScopeLog::create([
                    'business_id' => $conversation->business_id,
                    'conversation_id' => $conversation->id,
                    'text' => $data['out_of_scope_text'] ?? '',
                    'reason' => $data['out_of_scope_reason'] ?? 'unspecified',
                    'created_at' => CarbonImmutable::now(),
                ]);
            }
        });

        // Dispatch outbound SMS if there's a reply and a phone-bound channel.
        if (! empty($data['reply_text'])
            && in_array($conversation->channel, ['sms', 'hybrid'], true)
            && $conversation->customer_phone_e164) {
            $twilioE164 = optional(
                PhoneNumber::query()->where('business_id', $conversation->business_id)->first()
            )?->number_e164;

            if ($twilioE164) {
                SendOutboundSms::dispatch(
                    $conversation->id,
                    $conversation->customer_phone_e164,
                    $twilioE164,
                    $data['reply_text'],
                );
            }
        }

        return response()->json(['accepted' => true]);
    }

    private function bumpCostRollup(string $businessId, array $data): void
    {
        $today = CarbonImmutable::now()->toDateString();

        try {
            DB::statement(<<<'SQL'
                INSERT INTO llm_cost_daily (business_id, date, tokens_in, tokens_out, cost_micros, updated_at)
                VALUES (?, ?, ?, ?, ?, NOW())
                ON CONFLICT (business_id, date) DO UPDATE SET
                  tokens_in = llm_cost_daily.tokens_in + EXCLUDED.tokens_in,
                  tokens_out = llm_cost_daily.tokens_out + EXCLUDED.tokens_out,
                  cost_micros = llm_cost_daily.cost_micros + EXCLUDED.cost_micros,
                  updated_at = NOW()
            SQL, [
                $businessId,
                $today,
                (int) ($data['tokens_in'] ?? 0),
                (int) ($data['tokens_out'] ?? 0),
                (int) ($data['cost_micros'] ?? 0),
            ]);
        } catch (QueryException) {
            // Fallback for tests on sqlite — best-effort.
            LlmCostDaily::query()->updateOrCreate(
                ['business_id' => $businessId, 'date' => $today],
                [
                    'tokens_in' => DB::raw('tokens_in + '.(int) ($data['tokens_in'] ?? 0)),
                    'tokens_out' => DB::raw('tokens_out + '.(int) ($data['tokens_out'] ?? 0)),
                    'cost_micros' => DB::raw('cost_micros + '.(int) ($data['cost_micros'] ?? 0)),
                ],
            );
        }
    }
}
