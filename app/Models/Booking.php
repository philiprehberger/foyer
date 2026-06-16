<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Symfony\Component\Uid\Ulid;

class Booking extends Model
{
    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'conversation_id', 'business_id', 'customer_phone_e164', 'address',
        'address_lat', 'address_lng', 'service_type_key', 'slot_start',
        'slot_end', 'google_calendar_event_id', 'confirmed_by',
        'confirmed_at', 'status',
    ];

    protected $casts = [
        'slot_start' => 'datetime',
        'slot_end' => 'datetime',
        'confirmed_at' => 'datetime',
        'address_lat' => 'float',
        'address_lng' => 'float',
    ];

    public const PENDING = 'pending';

    public const CONFIRMED = 'confirmed';

    public const CANCELED = 'canceled';

    public const COMPLETED = 'completed';

    protected static function booted(): void
    {
        static::creating(function (Booking $b) {
            if (! $b->id) {
                $b->id = (string) new Ulid;
            }
        });
    }

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }
}
