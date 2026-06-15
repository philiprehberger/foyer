<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MessageDelivery extends Model
{
    protected $fillable = [
        'message_id', 'twilio_sid', 'status', 'error_code', 'error_message',
    ];

    public function message(): BelongsTo
    {
        return $this->belongsTo(Message::class);
    }
}
