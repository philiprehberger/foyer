<?php

namespace App\Http\Resources;

use App\Models\DeliveryAttempt;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin DeliveryAttempt
 */
class DeliveryAttemptResource extends JsonResource
{
    public static $wrap = null;

    public function toArray(Request $request): array
    {
        return [
            'attempt_number' => $this->attempt_number,
            'request_signature' => $this->request_signature,
            'response_status' => $this->response_status,
            'response_headers' => $this->response_headers,
            'response_body_snippet' => $this->response_body_snippet,
            'latency_ms' => $this->latency_ms,
            'error_code' => $this->error_code,
            'attempted_at' => $this->attempted_at->toIso8601String(),
        ];
    }
}
