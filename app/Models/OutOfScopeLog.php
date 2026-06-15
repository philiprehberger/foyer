<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OutOfScopeLog extends Model
{
    protected $table = 'out_of_scope_log';

    public $timestamps = false;

    protected $fillable = [
        'conversation_id', 'business_id', 'text', 'reason',
        'suggested_service_category', 'created_at',
    ];

    protected $casts = [
        'created_at' => 'datetime',
    ];
}
