<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Symfony\Component\Uid\Ulid;

class PhoneNumber extends Model
{
    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'business_id', 'number_e164', 'messaging_service_sid',
        'campaign_sid', 'provisioned_at', 'released_at', 'status',
    ];

    protected $casts = [
        'provisioned_at' => 'datetime',
        'released_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (PhoneNumber $p) {
            if (! $p->id) {
                $p->id = (string) new Ulid;
            }
        });
    }

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }
}
