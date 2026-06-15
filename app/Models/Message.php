<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Symfony\Component\Uid\Ulid;

class Message extends Model
{
    public $timestamps = false;

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'conversation_id', 'external_id', 'role', 'text', 'attachments',
        'phase', 'intent', 'model', 'tokens_in', 'tokens_out', 'cost_micros',
        'created_at',
    ];

    protected $casts = [
        'attachments' => 'array',
        'intent' => 'array',
        'created_at' => 'datetime',
        'tokens_in' => 'integer',
        'tokens_out' => 'integer',
        'cost_micros' => 'integer',
    ];

    public const ROLE_CUSTOMER = 'customer';
    public const ROLE_AGENT = 'agent';
    public const ROLE_OWNER = 'owner';
    public const ROLE_SYSTEM = 'system';
    public const ROLE_TOOL = 'tool';

    protected static function booted(): void
    {
        static::creating(function (Message $m) {
            if (! $m->id) {
                $m->id = (string) new Ulid;
            }
        });
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    public function delivery(): HasOne
    {
        return $this->hasOne(MessageDelivery::class);
    }
}
