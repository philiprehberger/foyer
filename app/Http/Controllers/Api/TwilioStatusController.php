<?php

namespace App\Http\Controllers\Api;

use App\Models\MessageDelivery;
use Illuminate\Http\Request;

/**
 * Twilio delivery-status callback receiver.
 *
 * Spec: https://www.twilio.com/docs/messaging/guides/track-outbound-message-status
 * Status codes that matter for alerting: 30007 (carrier filter), 30008 (unknown).
 */
class TwilioStatusController
{
    public function __invoke(Request $request)
    {
        $sid = (string) $request->input('MessageSid', '');
        $status = (string) $request->input('MessageStatus', '');
        $errorCode = $request->input('ErrorCode');

        if ($sid === '') {
            return response()->noContent(204);
        }

        $delivery = MessageDelivery::query()->where('twilio_sid', $sid)->first();

        if ($delivery) {
            $delivery->status = $status;
            if ($errorCode !== null) {
                $delivery->error_code = (string) $errorCode;
            }
            $delivery->save();
        }

        return response()->noContent(204);
    }
}
