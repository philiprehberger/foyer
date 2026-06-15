<?php

namespace App\Services;

/**
 * Best-effort E.164 normalization. Phase 2 keeps the surface area small —
 * we accept the Twilio-supplied E.164 directly for SMS, and only normalize
 * what the customer types into the web widget's phone-verification step.
 *
 * For US-centric demo traffic the rule is: strip everything but digits + "+";
 * if no "+" prefix and 10 digits, assume US (+1).
 */
class PhoneNormalizer
{
    public static function toE164(string $input, string $defaultRegion = 'US'): ?string
    {
        $cleaned = preg_replace('/[^\d+]/', '', $input) ?? '';

        if ($cleaned === '') {
            return null;
        }

        if (str_starts_with($cleaned, '+')) {
            return preg_match('/^\+[1-9]\d{6,14}$/', $cleaned) ? $cleaned : null;
        }

        if ($defaultRegion === 'US' && strlen($cleaned) === 10) {
            return '+1'.$cleaned;
        }

        if ($defaultRegion === 'US' && strlen($cleaned) === 11 && str_starts_with($cleaned, '1')) {
            return '+'.$cleaned;
        }

        return null;
    }
}
