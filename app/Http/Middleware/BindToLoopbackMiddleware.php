<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

/**
 * Rejects any internal-API request that did not arrive over the loopback
 * interface. Paired with InternalHmacMiddleware.
 *
 * The FastAPI agent worker calls Laravel at 127.0.0.1. If a request arrives
 * from a different network, something is wrong — fail closed with 403.
 */
class BindToLoopbackMiddleware
{
    public function handle(Request $request, Closure $next): mixed
    {
        $allowed = (string) config('foyer.internal_bind', '127.0.0.1');
        $remote = (string) $request->ip();

        $isLocal = in_array($remote, ['127.0.0.1', '::1'], true) || $remote === $allowed;

        if (! $isLocal) {
            return response()->json(
                [
                    'type' => 'urn:foyer:problem:internal-api-forbidden',
                    'title' => 'Internal API is restricted to the loopback interface.',
                    'status' => 403,
                    'remote' => $remote,
                ],
                403,
                ['Content-Type' => 'application/problem+json']
            );
        }

        return $next($request);
    }
}
