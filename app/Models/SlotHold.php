<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Symfony\Component\Uid\Ulid;

class SlotHold extends Model
{
    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'conversation_id', 'business_id', 'slot_start', 'slot_end',
        'service_type_key', 'google_calendar_event_id', 'status',
        'proposed_at', 'expires_at', 'released_at',
    ];

    protected $casts = [
        'slot_start' => 'datetime',
        'slot_end' => 'datetime',
        'proposed_at' => 'datetime',
        'expires_at' => 'datetime',
        'released_at' => 'datetime',
    ];

    public const ACTIVE = 'active';

    public const EXPIRED = 'expired';

    public const CONFIRMED = 'confirmed';

    public const RELEASED = 'released';

    protected static function booted(): void
    {
        static::creating(function (SlotHold $s) {
            if (! $s->id) {
                $s->id = (string) new Ulid;
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
