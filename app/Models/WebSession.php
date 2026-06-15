<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Symfony\Component\Uid\Ulid;

class WebSession extends Model
{
    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'business_id', 'token_hash', 'ip', 'customer_phone_e164',
        'otp_hash', 'otp_sent_at', 'otp_attempts', 'phone_verified_at',
        'expires_at',
    ];

    protected $casts = [
        'otp_sent_at' => 'datetime',
        'phone_verified_at' => 'datetime',
        'expires_at' => 'datetime',
        'otp_attempts' => 'integer',
    ];

    protected static function booted(): void
    {
        static::creating(function (WebSession $s) {
            if (! $s->id) {
                $s->id = (string) new Ulid;
            }
        });
    }

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }
}
