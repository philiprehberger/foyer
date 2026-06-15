<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class IdempotencyKey extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'key', 'endpoint', 'user_id', 'business_id', 'response_status',
        'response_body', 'created_at', 'expires_at',
    ];

    protected $casts = [
        'response_body' => 'array',
        'created_at' => 'datetime',
        'expires_at' => 'datetime',
    ];
}
