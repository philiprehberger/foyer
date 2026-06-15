<?php

namespace App\Http\Controllers\Api;

use App\Models\Conversation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ConversationsController
{
    public function index(Request $request): JsonResponse
    {
        $userBusinessIds = $request->user()->businesses()->pluck('businesses.id')->all();

        $query = Conversation::query()->whereIn('business_id', $userBusinessIds);

        if ($request->filled('business_id')) {
            $bid = (string) $request->query('business_id');
            if (! in_array($bid, $userBusinessIds, true)) {
                abort(403);
            }
            $query->where('business_id', $bid);
        }

        if ($request->filled('phase')) {
            $query->whereHas('messages', fn ($q) => $q->where('phase', $request->query('phase')));
        }

        $rows = $query->orderByDesc('last_message_at')->paginate(50);

        return response()->json([
            'data' => $rows->items(),
            'page' => $rows->currentPage(),
            'per_page' => $rows->perPage(),
            'total' => $rows->total(),
        ]);
    }

    public function show(Request $request, string $conversationId): JsonResponse
    {
        $conversation = Conversation::query()->with('messages')->findOrFail($conversationId);

        if (! $request->user()->ownsBusiness($conversation->business_id)) {
            abort(403);
        }

        return response()->json([
            'conversation' => $conversation,
            'messages' => $conversation->messages,
            'current_phase' => $conversation->currentPhase(),
        ]);
    }
}
