<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Symfony\Component\Uid\Ulid;

class ServiceType extends Model
{
    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'business_id', 'key', 'label', 'description',
        'est_duration_min', 'requires_photos',
    ];

    protected $casts = [
        'requires_photos' => 'boolean',
        'est_duration_min' => 'integer',
    ];

    protected static function booted(): void
    {
        static::creating(function (ServiceType $s) {
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
