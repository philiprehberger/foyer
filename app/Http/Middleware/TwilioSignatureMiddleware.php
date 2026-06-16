<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Validates Twilio's X-Twilio-Signature on inbound webhooks.
 *
 * Fail-closed: a missing or mismatched signature returns 403. Clock-skew is
 * not part of the Twilio scheme — the signature covers the full URL + sorted
 * params (or raw body for JSON), keyed by the account's auth token.
 *
 * Spec: https://www.twilio.com/docs/usage/security#validating-requests
 */
class TwilioSignatureMiddleware
{
    public function handle(Request $request, Closure $next): mixed
    {
        $token = (string) config('services.twilio.auth_token', '');

        if ($token === '') {
            return $this->problem(503, 'twilio-not-configured',
                'Twilio auth token is not configured.');
        }

        $provided = (string) $request->header('X-Twilio-Signature', '');

        if ($provided === '') {
            return $this->problem(403, 'twilio-signature-missing',
                'X-Twilio-Signature header is required.');
        }

        $url = $request->fullUrl();
        $params = $request->isMethod('POST') ? $request->post() : [];

        $expected = $this->compute($url, $params, $token);

        if (! hash_equals($expected, $provided)) {
            return $this->problem(403, 'twilio-signature-invalid',
                'X-Twilio-Signature did not validate.');
        }

        return $next($request);
    }

    /**
     * Twilio's recipe: sort POST params by key, append "{key}{value}" pairs
     * to the URL, HMAC-SHA1 with the auth token, base64-encode.
     *
     * @param  array<string, mixed>  $params
     */
    public function compute(string $url, array $params, string $token): string
    {
        ksort($params);
        $data = $url;
        foreach ($params as $k => $v) {
            $data .= $k.(is_array($v) ? json_encode($v) : (string) $v);
        }

        return base64_encode(hash_hmac('sha1', $data, $token, true));
    }

    private function problem(int $status, string $type, string $detail): Response
    {
        return response()->json(
            [
                'type' => "urn:foyer:problem:{$type}",
                'title' => $detail,
                'status' => $status,
            ],
            $status,
            ['Content-Type' => 'application/problem+json']
        );
    }
}
