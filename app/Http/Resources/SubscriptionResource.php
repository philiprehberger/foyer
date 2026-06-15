<?php

namespace App\Http\Resources;

use App\Models\Subscription;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Subscription
 */
class SubscriptionResource extends JsonResource
{
    public static $wrap = null;

    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'url' => $this->url,
            'event_filter' => $this->event_filter,
            'state' => $this->state,
            'consecutive_failures' => $this->consecutive_failures,
            'paused_at' => $this->paused_at?->toIso8601String(),
            'secret_rotated_at' => $this->secret_rotated_at?->toIso8601String(),
            'created_at' => $this->created_at->toIso8601String(),
            'updated_at' => $this->updated_at->toIso8601String(),
        ];
    }

    public function withSecret(string $plaintextSecret): array
    {
        return array_merge($this->resolve(), [
            'signing_secret' => $plaintextSecret,
        ]);
    }
}
