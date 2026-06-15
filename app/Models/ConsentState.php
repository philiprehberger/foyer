<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ConsentState extends Model
{
    protected $table = 'consent_state';

    public $timestamps = false;

    protected $fillable = [
        'customer_phone_e164', 'twilio_number_e164', 'state', 'last_change_at',
    ];

    protected $casts = [
        'last_change_at' => 'datetime',
    ];

    public const SUBSCRIBED = 'subscribed';
    public const STOPPED = 'stopped';

    public static function isStopped(string $customerE164, string $twilioE164): bool
    {
        $row = static::query()
            ->where('customer_phone_e164', $customerE164)
            ->where('twilio_number_e164', $twilioE164)
            ->first();

        return $row && $row->state === self::STOPPED;
    }
}
