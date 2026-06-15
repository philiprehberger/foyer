<?php

namespace App\Http\Controllers\Api;

use App\Models\Business;
use App\Models\WebSession;
use App\Services\ConsentStateMachine;
use App\Services\PhoneNormalizer;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Widget session lifecycle.
 *
 * - POST /v1/web/sessions          — mint a short-lived session token bound
 *                                    to the embedded business + caller IP.
 * - POST /v1/web/sessions/{id}/verify-phone
 *                                  — OTP issue + verify for cross-channel
 *                                    resume. Phase 6 wires SMS delivery; for
 *                                    Phase 1-5 the controller is end-to-end
 *                                    sound but no SMS is actually sent.
 */
class WebSessionController
{
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'business_id' => 'required|string|size:26',
        ]);

        $business = Business::query()->findOrFail($data['business_id']);

        $token = bin2hex(random_bytes(24));
        $hash = hash('sha256', $token);

        $session = WebSession::create([
            'business_id' => $business->id,
            'token_hash' => $hash,
            'ip' => $request->ip(),
            'expires_at' => CarbonImmutable::now()->addHours(2),
        ]);

        return response()->json([
            'session_id' => $session->id,
            'token' => $token,
            'expires_at' => $session->expires_at->toIso8601String(),
        ], 201);
    }

    public function verifyPhone(Request $request, string $sessionId): JsonResponse
    {
        $session = WebSession::query()->findOrFail($sessionId);

        if ($session->expires_at->isPast()) {
            return response()->json([
                'type' => 'urn:foyer:problem:web-session-expired',
                'title' => 'Web session has expired.',
                'status' => 410,
            ], 410, ['Content-Type' => 'application/problem+json']);
        }

        $data = $request->validate([
            'phone' => 'required|string|max:32',
            'code' => 'nullable|string|max:8',
        ]);

        $e164 = PhoneNormalizer::toE164($data['phone']);

        if (! $e164) {
            return response()->json([
                'type' => 'urn:foyer:problem:phone-invalid',
                'title' => 'Phone number did not normalize to E.164.',
                'status' => 400,
            ], 400, ['Content-Type' => 'application/problem+json']);
        }

        // Issue phase: no code supplied → mint and store a 6-digit OTP.
        if (empty($data['code'])) {
            // STOP'd numbers cannot resume via web either.
            $business = Business::query()->findOrFail($session->business_id);
            $twilioNumber = optional($business->phoneNumbers()->first())->number_e164;
            if ($twilioNumber && ConsentStateMachine::isStopped($e164, $twilioNumber)) {
                return response()->json([
                    'type' => 'urn:foyer:problem:consent-blocked',
                    'title' => 'This number has opted out — please contact the business directly.',
                    'status' => 403,
                ], 403, ['Content-Type' => 'application/problem+json']);
            }

            $code = (string) random_int(100000, 999999);
            $session->customer_phone_e164 = $e164;
            $session->otp_hash = hash('sha256', $code);
            $session->otp_sent_at = CarbonImmutable::now();
            $session->otp_attempts = 0;
            $session->save();

            // Phase 6 wires the actual SMS dispatch via SendOutboundSms.
            return response()->json(['stage' => 'code_sent']);
        }

        // Verify phase: supplied code.
        if (! $session->otp_hash || ! $session->otp_sent_at) {
            return response()->json([
                'type' => 'urn:foyer:problem:otp-not-issued',
                'title' => 'No OTP has been issued for this session.',
                'status' => 400,
            ], 400, ['Content-Type' => 'application/problem+json']);
        }

        if ($session->otp_attempts >= 5) {
            return response()->json([
                'type' => 'urn:foyer:problem:otp-attempts-exceeded',
                'title' => 'Too many OTP attempts.',
                'status' => 429,
            ], 429, ['Content-Type' => 'application/problem+json']);
        }

        $session->otp_attempts++;
        $session->save();

        if (! hash_equals($session->otp_hash, hash('sha256', $data['code']))) {
            return response()->json([
                'type' => 'urn:foyer:problem:otp-mismatch',
                'title' => 'OTP did not match.',
                'status' => 400,
            ], 400, ['Content-Type' => 'application/problem+json']);
        }

        $session->phone_verified_at = CarbonImmutable::now();
        $session->customer_phone_e164 = $e164;
        $session->otp_hash = null;
        $session->save();

        return response()->json([
            'stage' => 'verified',
            'phone_verified_at' => $session->phone_verified_at->toIso8601String(),
        ]);
    }
}
