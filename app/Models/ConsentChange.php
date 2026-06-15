<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ConsentChange extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'customer_phone_e164', 'twilio_number_e164', 'from_state', 'to_state',
        'source_message_id', 'created_at',
    ];

    protected $casts = [
        'created_at' => 'datetime',
    ];
}
