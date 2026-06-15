<?php

namespace App\Http\Controllers\Internal;

use App\Models\Business;
use App\Models\Conversation;
use App\Models\Message;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

/**
 * GET /_internal/conversations/{conversation}/turn-context
 *
 * The FastAPI agent worker calls this to load the state it needs to drive
 * the next turn. The response shape must match the worker's TurnContext
 * Pydantic model in `workers/agent/contracts.py`. Field names are flat at
 * the top level by design — the worker treats this as a single source of
 * truth and does not chain extra HTTP round trips for sub-data.
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
            ->values();

        $messages = $recent->map(fn (Message $m) => [
            'role' => $m->role,
            'text' => (string) ($m->text ?? ''),
            'phase' => $m->phase,
        ])->all();

        $lastUserMessage = $recent->last(fn (Message $m) => $m->role === Message::ROLE_CUSTOMER);
        $costToday = (int) (DB::table('llm_cost_daily')
            ->where('business_id', $business->id)
            ->whereDate('date', now()->toDateString())
            ->value('cost_micros') ?? 0);

        return response()->json([
            'conversation_id' => $conversation->id,
            'business' => [
                'id' => $business->id,
                'name' => $business->name,
                'timezone' => $business->timezone,
                'persona' => $business->persona ?: 'professional',
                'system_prompt_suffix' => (string) ($business->system_prompt_suffix ?? ''),
                'service_types' => $business->serviceTypes->pluck('key')->all(),
                'service_area_description' => $this->describeServiceArea($business),
                'business_hours_description' => $this->describeBusinessHours($business),
                'human_handoff_threshold' => (float) $business->human_handoff_threshold,
                'cost_ceiling_micros' => (int) $business->cost_ceiling_micros,
                'cheap_mode_model' => 'claude-haiku-4-5',
            ],
            'current_phase' => $conversation->currentPhase() ?: 'greet',
            'messages' => $messages,
            'last_user_message' => (string) ($lastUserMessage?->text ?? ''),
            'cumulative_cost_micros_today' => $costToday,
        ]);
    }

    private function describeServiceArea(Business $business): string
    {
        $area = $business->service_area ?? [];
        $type = $area['type'] ?? null;

        if ($type === 'zip_codes') {
            $codes = $area['codes'] ?? [];

            return $codes ? 'ZIP codes: '.implode(', ', $codes) : '';
        }
        if ($type === 'radius') {
            return sprintf(
                'within %s km of (%s, %s)',
                $area['radius_km'] ?? '?',
                $area['center_lat'] ?? '?',
                $area['center_lng'] ?? '?',
            );
        }

        return '';
    }

    private function describeBusinessHours(Business $business): string
    {
        $hours = $business->business_hours ?? [];
        $lines = [];
        foreach (['mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun'] as $day) {
            foreach ($hours[$day] ?? [] as $window) {
                $lines[] = sprintf('%s %s-%s', ucfirst($day), $window['open'] ?? '?', $window['close'] ?? '?');
            }
        }

        return implode('; ', $lines);
    }
}
