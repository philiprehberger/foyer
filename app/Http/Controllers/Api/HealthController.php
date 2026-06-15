<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;

class HealthController
{
    public function __invoke(): JsonResponse
    {
        $checks = [
            'database' => $this->pingDb(),
            'redis' => $this->pingRedis(),
        ];

        $healthy = ! in_array(false, $checks, true);

        return response()->json(
            [
                'healthy' => $healthy,
                'checks' => $checks,
                'version' => config('app.version', 'dev'),
            ],
            $healthy ? 200 : 503,
        );
    }

    private function pingDb(): bool
    {
        try {
            DB::connection()->getPdo();

            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    private function pingRedis(): bool
    {
        try {
            return Redis::connection()->ping() !== false;
        } catch (\Throwable) {
            return false;
        }
    }
}
