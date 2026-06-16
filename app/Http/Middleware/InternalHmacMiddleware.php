<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Authenticates internal API requests from the FastAPI agent worker.
 *
 * Every request must carry `X-Foyer-Internal-Sig: hmac_sha256(secret, body)`.
 * The shared secret lives in FOYER_INTERNAL_SECRET. Documented in
 * docs/architecture/internal-api.md.
 */
class InternalHmacMiddleware
{
    public function handle(Request $request, Closure $next): mixed
    {
        $secret = (string) config('foyer.internal_secret', '');

        if ($secret === '') {
            return $this->problem(503, 'internal-api-disabled', 'Internal API secret is not configured.');
        }

        $provided = (string) $request->header('X-Foyer-Internal-Sig', '');

        if ($provided === '') {
            return $this->problem(401, 'missing-signature', 'X-Foyer-Internal-Sig header required.');
        }

        $expected = hash_hmac('sha256', $request->getContent(), $secret);

        if (! hash_equals($expected, $provided)) {
            return $this->problem(403, 'invalid-signature', 'X-Foyer-Internal-Sig did not validate.');
        }

        return $next($request);
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
