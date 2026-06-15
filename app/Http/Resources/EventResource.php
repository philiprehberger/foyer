<?php

namespace App\Http\Resources;

use App\Models\Event;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Serializes an Event matching the Event schema in openapi/spec.yaml.
 *
 * @mixin Event
 */
class EventResource extends JsonResource
{
    public static $wrap = null;

    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type,
            'payload' => $this->payload,
            'idempotency_key' => $this->idempotency_key,
            'source_ip' => $this->source_ip,
            'created_at' => $this->created_at->toIso8601String(),
            'deliveries_summary' => $this->deliveriesSummary(),
        ];
    }
}
