<?php

namespace App\Http\Controllers\Api;

use App\Jobs\ProvisionTwilioNumber;
use App\Models\Business;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class NumbersController
{
    public function provision(Request $request): JsonResponse
    {
        $data = $request->validate([
            'business_id' => 'required|string|size:26',
            'area_code' => 'nullable|string|size:3',
        ]);

        $business = Business::query()->findOrFail($data['business_id']);

        if (! $request->user()->ownsBusiness($business->id)) {
            abort(403);
        }

        $jobId = (string) Str::ulid();

        ProvisionTwilioNumber::dispatch($jobId, $business->id, $data['area_code'] ?? null);

        return response()->json([
            'job_id' => $jobId,
            'status' => 'queued',
        ], 202, ['Location' => "/v1/jobs/{$jobId}"]);
    }

    public function jobStatus(Request $request, string $jobId): JsonResponse
    {
        $cached = cache()->get("foyer:jobs:{$jobId}");

        if (! $cached) {
            return response()->json([
                'job_id' => $jobId,
                'status' => 'unknown',
            ], 404);
        }

        return response()->json($cached);
    }
}
