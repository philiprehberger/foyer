<?php

namespace App\Http\Controllers\Internal;

use App\Models\AuditLog;
use App\Models\Conversation;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * POST /_internal/conversations/{conversation}/escalate
 *
 * Worker signals "I cannot drive this turn — hand it off to a human."
 * Phase transitions to human_handoff and the conversation is marked
 * completed. The owner sees it surface in the pending-bookings dashboard
 * with a banner that the agent escalated.
 */
class AgentEscalateController
{
    public function __invoke(Request $request, string $conversationId): JsonResponse
    {
        $data = $request->validate([
            'reason' => 'required|string|max:128',
            'detail' => 'nullable|string|max:1000',
        ]);

        $conversation = Conversation::query()->findOrFail($conversationId);

        $conversation->forceFill([
            'completed_at' => CarbonImmutable::now(),
            'state' => array_merge(
                $conversation->state ?? [],
                ['escalated' => true, 'escalate_reason' => $data['reason']],
            ),
        ])->save();

        AuditLog::create([
            'actor_type' => 'agent',
            'business_id' => $conversation->business_id,
            'event' => 'agent.escalated',
            'payload' => $data,
            'created_at' => CarbonImmutable::now(),
        ]);

        return response()->json(['accepted' => true]);
    }
}
