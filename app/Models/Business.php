<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Symfony\Component\Uid\Ulid;

class Business extends Model
{
    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'name', 'slug', 'timezone', 'quiet_hours_start', 'quiet_hours_end',
        'google_calendar_id', 'oauth_refresh_token_ref', 'persona',
        'system_prompt_suffix', 'service_area', 'business_hours',
        'blocked_dates', 'min_lead_minutes', 'max_lead_days',
        'human_handoff_phone', 'human_handoff_threshold', 'cost_ceiling_micros',
        'kill_switch_at',
    ];

    protected $casts = [
        'service_area' => 'array',
        'business_hours' => 'array',
        'blocked_dates' => 'array',
        'human_handoff_threshold' => 'float',
        'kill_switch_at' => 'datetime',
        'cost_ceiling_micros' => 'integer',
        'min_lead_minutes' => 'integer',
        'max_lead_days' => 'integer',
    ];

    protected static function booted(): void
    {
        static::creating(function (Business $b) {
            if (! $b->id) {
                $b->id = (string) new Ulid;
            }
        });
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'business_memberships')
            ->withPivot('role')->withTimestamps();
    }

    public function phoneNumbers(): HasMany
    {
        return $this->hasMany(PhoneNumber::class);
    }

    public function serviceTypes(): HasMany
    {
        return $this->hasMany(ServiceType::class);
    }

    public function conversations(): HasMany
    {
        return $this->hasMany(Conversation::class);
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }

    public function isKilled(): bool
    {
        return $this->kill_switch_at !== null;
    }
}
