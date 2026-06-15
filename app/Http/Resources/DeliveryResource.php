<?php

namespace App\Http\Resources;

use App\Models\Delivery;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Delivery
 */
class DeliveryResource extends JsonResource
{
    public static $wrap = null;

    public function __construct($resource, public bool $includeAttempts = false)
    {
        parent::__construct($resource);
    }

    public function toArray(Request $request): array
    {
        $data = [
            'id' => $this->id,
            'event_id' => $this->event_id,
            'subscription_id' => $this->subscription_id,
            'status' => $this->status,
            'attempts_made' => $this->attempts_made,
            'next_attempt_at' => $this->next_attempt_at?->toIso8601String(),
            'final_status_code' => $this->final_status_code,
            'completed_at' => $this->completed_at?->toIso8601String(),
            'created_at' => $this->created_at->toIso8601String(),
        ];

        if ($this->includeAttempts) {
            $data['attempts'] = DeliveryAttemptResource::collection($this->attempts)->resolve();
        }

        return $data;
    }
}
