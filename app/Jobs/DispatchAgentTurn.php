<?php

namespace App\Jobs;

use App\Models\Conversation;
use App\Models\Message;
use Carbon\CarbonImmutable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;

/**
 * Hand off the freshly-inserted inbound message to the FastAPI agent worker.
 *
 * Laravel does not run the LLM. It dispatches this job to Redis, which the
 * FastAPI worker pulls from (or — in the simpler shape — Laravel POSTs the
 * payload here to the worker's HTTP endpoint).
 *
 * Either way, the contract is the AgentTurn JSON Schema at
 * `infra/contracts/agent-turn.schema.json`.
 */
class DispatchAgentTurn implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $backoff = 5;

    public function __construct(
        public readonly string $conversationId,
        public readonly string $messageId,
    ) {
        $this->onQueue('agent');
    }

    public function handle(): void
    {
        $conversation = Conversation::query()->findOrFail($this->conversationId);
        $message = Message::query()->findOrFail($this->messageId);

        $payload = [
            'schema_version' => 1,
            'conversation_id' => $conversation->id,
            'message_id' => $message->id,
            'business_id' => $conversation->business_id,
            'channel' => $conversation->channel,
            'enqueued_at' => CarbonImmutable::now()->toIso8601String(),
        ];

        $body = json_encode($payload, JSON_THROW_ON_ERROR);
        $sig = hash_hmac('sha256', $body, (string) config('foyer.internal_secret'));

        Http::withHeaders([
            'X-Foyer-Internal-Sig' => $sig,
            'Content-Type' => 'application/json',
        ])->timeout(30)->post(
            rtrim((string) config('foyer.agent_worker_url'), '/').'/run-turn',
            $payload,
        )->throwIfServerError();
    }
}
