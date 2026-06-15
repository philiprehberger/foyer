<?php

namespace App\Http\Controllers\Internal;

use App\Models\Business;
use App\Models\Conversation;
use App\Models\Message;
use Illuminate\Http\JsonResponse;

/**
 * GET /_internal/conversations/{conversation}/turn-context
 *
 * The FastAPI agent worker calls this to load the state it needs to drive
 * the next turn — last N messages, current phase, business config, persona,
 * scope guardrails, today's spend ceiling progress.
 *
 * Auth: BindToLoopback + InternalHmac middleware (see routes/api.php).
 */
class AgentTurnContextController
{
    private const TURN_WINDOW_MESSAGES = 20;

    public function __invoke(string $conversationId): JsonResponse
    {
        $conversation = Conversation::query()->findOrFail($conversationId);
        $business = Business::query()->findOrFail($conversation->business_id);

        $recent = $conversation->messages()
            ->orderByDesc('created_at')
            ->limit(self::TURN_WINDOW_MESSAGES)
            ->get()
            ->reverse()
            ->values()
            ->map(fn (Message $m) => [
                'id' => $m->id,
                'role' => $m->role,
                'text' => $m->text,
                'phase' => $m->phase,
                'attachments' => $m->attachments,
                'created_at' => $m->created_at?->toIso8601String(),
            ])->all();

        $cost = \DB::table('llm_cost_daily')
            ->where('business_id', $business->id)
            ->whereDate('date', now()->toDateString())
            ->first();

        return response()->json([
            'conversation' => [
                'id' => $conversation->id,
                'channel' => $conversation->channel,
                'current_phase' => $conversation->currentPhase(),
                'state' => $conversation->state,
                'phone_verified_at' => $conversation->phone_verified_at?->toIso8601String(),
            ],
            'business' => [
                'id' => $business->id,
                'name' => $business->name,
                'persona' => $business->persona,
                'system_prompt_suffix' => $business->system_prompt_suffix,
                'timezone' => $business->timezone,
                'service_area' => $business->service_area,
                'business_hours' => $business->business_hours,
                'blocked_dates' => $business->blocked_dates,
                'min_lead_minutes' => $business->min_lead_minutes,
                'max_lead_days' => $business->max_lead_days,
                'human_handoff_threshold' => $business->human_handoff_threshold,
                'cost_ceiling_micros' => $business->cost_ceiling_micros,
                'service_types' => $business->serviceTypes->map(fn ($t) => [
                    'key' => $t->key,
                    'label' => $t->label,
                    'description' => $t->description,
                    'est_duration_min' => $t->est_duration_min,
                    'requires_photos' => $t->requires_photos,
                ])->all(),
                'kill_switch_active' => $business->isKilled(),
            ],
            'cost_today' => [
                'tokens_in' => (int) ($cost->tokens_in ?? 0),
                'tokens_out' => (int) ($cost->tokens_out ?? 0),
                'cost_micros' => (int) ($cost->cost_micros ?? 0),
                'remaining_micros' => max(
                    0,
                    $business->cost_ceiling_micros - (int) ($cost->cost_micros ?? 0),
                ),
            ],
            'recent_messages' => $recent,
        ]);
    }
}
