<?php

namespace App\Http\Controllers\Api;

use App\Models\OutOfScopeLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OutOfScopeLogController
{
    public function __invoke(Request $request): JsonResponse
    {
        $userBusinessIds = $request->user()->businesses()->pluck('businesses.id')->all();

        $rows = OutOfScopeLog::query()
            ->whereIn('business_id', $userBusinessIds)
            ->orderByDesc('created_at')
            ->paginate(50);

        return response()->json([
            'data' => $rows->items(),
            'page' => $rows->currentPage(),
            'per_page' => $rows->perPage(),
            'total' => $rows->total(),
        ]);
    }
}
