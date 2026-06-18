<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Symfony\Component\Uid\Ulid;

class Conversation extends Model
{
    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'business_id', 'customer_phone_e164', 'web_session_id', 'channel',
        'state', 'phone_verified_at', 'started_at', 'last_message_at',
        'abandoned_at', 'completed_at',
    ];

    protected $casts = [
        'state' => 'array',
        'phone_verified_at' => 'datetime',
        'started_at' => 'datetime',
        'last_message_at' => 'datetime',
        'abandoned_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (Conversation $c) {
            if (! $c->id) {
                $c->id = (string) new Ulid;
            }
            if (! $c->started_at) {
                $c->started_at = CarbonImmutable::now();
            }
        });
    }

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    public function messages(): HasMany
    {
        return $this->hasMany(Message::class)->orderBy('created_at');
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }

    public function slotHolds(): HasMany
    {
        return $this->hasMany(SlotHold::class);
    }

    /**
     * Derive the current phase from the most-recent assistant/system message.
     * Phase is NOT stored on the conversation row — the plan calls this out
     * explicitly and tests assert it.
     */
    public function currentPhase(): ?string
    {
        // Only agent-intent rows carry meaningful phase. The 'outbound:' rows
        // SendOutboundSms inserts are delivery records — their phase is a
        // stale read of currentPhase() at dispatch time, and including them
        // here creates a feedback loop that pins the conversation to the
        // previous phase forever.
        // ->reorder() is required because the messages() relationship is
        // declared with a baked-in orderBy('created_at') ASC. Without the
        // reorder, our DESC clauses get appended after the ASC and have no
        // effect — value('phase') then returns the OLDEST matching row, not
        // the newest, and the conversation regresses to the first agent
        // turn's phase on every read.
        return $this->messages()
            ->whereNotNull('phase')
            ->where('external_id', 'not like', 'outbound:%')
            ->reorder('created_at', 'desc')
            ->orderBy('id', 'desc')
            ->value('phase');
    }
}
