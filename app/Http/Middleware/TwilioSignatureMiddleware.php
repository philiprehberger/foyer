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

        // Twilio's official validators check the URL both with and without
        // an explicit default port — we have to accept either. Their
        // server-side signing can include or omit ":443" depending on the
        // network path / load balancer.
        $parsed = parse_url($url);
        $urlWithPort = null;
        if ($parsed !== false && ! isset($parsed['port'])) {
            $port = ($parsed['scheme'] ?? 'https') === 'https' ? 443 : 80;
            $urlWithPort = sprintf(
                '%s://%s:%d%s%s',
                $parsed['scheme'] ?? 'https',
                $parsed['host'] ?? '',
                $port,
                $parsed['path'] ?? '',
                isset($parsed['query']) ? '?'.$parsed['query'] : '',
            );
        }

        $expected = $this->compute($url, $params, $token);
        $expectedWithPort = $urlWithPort !== null ? $this->compute($urlWithPort, $params, $token) : null;

        $matched = hash_equals($expected, $provided)
            || ($expectedWithPort !== null && hash_equals($expectedWithPort, $provided));

        if (! $matched) {
            // Build the canonical string we hashed so we can diff against
            // Twilio's. Truncate body to avoid log-line bloat from long SMS.
            $sorted = $params;
            ksort($sorted);
            $canonical = $url;
            foreach ($sorted as $k => $v) {
                $val = is_array($v) ? json_encode($v) : (string) $v;
                $canonical .= $k.$val;
            }

            \Illuminate\Support\Facades\Log::warning('twilio.signature.invalid', [
                'url_used' => $url,
                'url_with_port' => $urlWithPort,
                'scheme' => $request->getScheme(),
                'fwd_proto' => $request->header('X-Forwarded-Proto'),
                'fwd_host' => $request->header('X-Forwarded-Host'),
                'host_header' => $request->header('Host'),
                'param_keys' => array_keys($sorted),
                'msg_sid' => $params['MessageSid'] ?? null,
                'from' => $params['From'] ?? null,
                'to' => $params['To'] ?? null,
                'body_snippet' => mb_substr((string) ($params['Body'] ?? ''), 0, 60),
                'expected_sig' => $expected,
                'expected_sig_with_port' => $expectedWithPort,
                'provided_sig' => $provided,
                'token_tail' => substr($token, -4),
                'canonical_len' => strlen($canonical),
                'canonical_head' => substr($canonical, 0, 200),
                'canonical_tail' => substr($canonical, -200),
            ]);

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
