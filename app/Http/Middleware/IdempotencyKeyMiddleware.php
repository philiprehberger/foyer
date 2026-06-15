<?php

namespace App\Http\Middleware;

use App\Models\IdempotencyKey;
use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Owner-facing mutating endpoints that side-effect outside Foyer
 * (Calendar lock + outbound SMS) require an Idempotency-Key header. Repeats
 * within the 24h window return the cached response without re-running the
 * controller — double-clicks collapse to one effect.
 */
class IdempotencyKeyMiddleware
{
    public function handle(Request $request, Closure $next): mixed
    {
        $key = (string) $request->header('Idempotency-Key', '');

        if ($key === '') {
            return $this->problem(400, 'idempotency-key-required',
                'Idempotency-Key header is required for this endpoint.');
        }

        if (strlen($key) > 255) {
            return $this->problem(400, 'idempotency-key-too-long',
                'Idempotency-Key header must be <= 255 characters.');
        }

        $endpoint = $request->method().' '.$request->path();
        $existing = IdempotencyKey::query()
            ->where('key', $key)
            ->where('endpoint', $endpoint)
            ->where('expires_at', '>', CarbonImmutable::now())
            ->first();

        if ($existing) {
            return response()->json(
                $existing->response_body ?? [],
                $existing->response_status ?? 200,
                ['X-Idempotent-Replayed' => 'true']
            );
        }

        $response = $next($request);

        if ($response->getStatusCode() >= 200 && $response->getStatusCode() < 300) {
            IdempotencyKey::query()->updateOrCreate(
                ['key' => $key, 'endpoint' => $endpoint],
                [
                    'user_id' => $request->user()?->id,
                    'business_id' => $request->route('business') ?? null,
                    'response_status' => $response->getStatusCode(),
                    'response_body' => json_decode((string) $response->getContent(), true) ?? [],
                    'created_at' => CarbonImmutable::now(),
                    'expires_at' => CarbonImmutable::now()->addDay(),
                ],
            );
        }

        return $response;
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
