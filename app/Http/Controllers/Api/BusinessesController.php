<?php

namespace App\Http\Controllers\Api;

use App\Models\AuditLog;
use App\Models\Business;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class BusinessesController
{
    public function updateScope(Request $request, string $businessId): JsonResponse
    {
        $business = Business::query()->findOrFail($businessId);
        if (! $request->user()->ownsBusiness($business->id)) {
            abort(403);
        }

        $data = $this->validateScope($request);

        $business->fill($data)->save();

        AuditLog::create([
            'actor_type' => 'user',
            'actor_id' => (string) $request->user()->id,
            'business_id' => $business->id,
            'event' => 'scope.updated',
            'payload' => $data,
            'ip' => $request->ip(),
            'created_at' => CarbonImmutable::now(),
        ]);

        return response()->json(['business' => $business->fresh()]);
    }

    public function toggleKillSwitch(Request $request, string $businessId): JsonResponse
    {
        $business = Business::query()->findOrFail($businessId);
        if (! $request->user()->ownsBusiness($business->id)) {
            abort(403);
        }

        $business->kill_switch_at = $business->kill_switch_at ? null : CarbonImmutable::now();
        $business->save();

        AuditLog::create([
            'actor_type' => 'user',
            'actor_id' => (string) $request->user()->id,
            'business_id' => $business->id,
            'event' => 'kill_switch.toggled',
            'payload' => ['active' => (bool) $business->kill_switch_at],
            'ip' => $request->ip(),
            'created_at' => CarbonImmutable::now(),
        ]);

        return response()->json([
            'business_id' => $business->id,
            'kill_switch_active' => (bool) $business->kill_switch_at,
        ]);
    }

    /**
     * Scope-config validation per the plan: garbage configs cannot be saved.
     */
    private function validateScope(Request $request): array
    {
        $data = $request->validate([
            'service_area' => 'nullable|array',
            'service_area.type' => 'nullable|in:zip_codes,radius',
            'service_area.codes' => 'array',
            'service_area.center_lat' => 'numeric',
            'service_area.center_lng' => 'numeric',
            'service_area.radius_km' => 'numeric|min:0.1',
            'business_hours' => 'nullable|array',
            'blocked_dates' => 'nullable|array',
            'min_lead_minutes' => 'integer|min:0',
            'max_lead_days' => 'integer|min:1|max:365',
            'quiet_hours_start' => 'date_format:H:i',
            'quiet_hours_end' => 'date_format:H:i',
            'human_handoff_threshold' => 'numeric|between:0,1',
            'persona' => 'in:professional,casual,gentle',
            'system_prompt_suffix' => 'nullable|string|max:2000',
            'timezone' => 'string|max:64',
        ]);

        Validator::make($data, [])
            ->after(function ($v) use ($data) {
                if (isset($data['min_lead_minutes'], $data['max_lead_days'])) {
                    if ($data['min_lead_minutes'] >= $data['max_lead_days'] * 1440) {
                        $v->errors()->add('min_lead_minutes',
                            'min_lead_minutes must be smaller than max_lead_days * 1440.');
                    }
                }
                if (isset($data['service_area']['type']) && $data['service_area']['type'] === 'zip_codes') {
                    if (empty($data['service_area']['codes'])) {
                        $v->errors()->add('service_area.codes',
                            'service_area.codes is required when type is zip_codes.');
                    }
                }
                if (isset($data['business_hours']) && $data['business_hours'] === []) {
                    $v->errors()->add('business_hours',
                        'business_hours must declare at least one open day.');
                }
            })
            ->validate();

        return $data;
    }
}
