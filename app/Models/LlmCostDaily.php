<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LlmCostDaily extends Model
{
    protected $table = 'llm_cost_daily';

    public $timestamps = false;

    protected $fillable = [
        'business_id', 'date', 'tokens_in', 'tokens_out', 'cost_micros',
        'parse_failures', 'updated_at',
    ];

    protected $casts = [
        'date' => 'date',
        'updated_at' => 'datetime',
    ];
}
