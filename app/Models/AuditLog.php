<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AuditLog extends Model
{
    protected $table = 'audit_log';

    public $timestamps = false;

    protected $fillable = [
        'actor_type', 'actor_id', 'business_id', 'event', 'payload', 'ip',
        'created_at',
    ];

    protected $casts = [
        'payload' => 'array',
        'created_at' => 'datetime',
    ];
}
