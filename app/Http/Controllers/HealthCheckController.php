<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;
use Throwable;

class HealthCheckController extends Controller
{
    /**
     * Perform application and infrastructure health checks.
     */
    public function __invoke(Request $request): JsonResponse
    {
        $status = 'healthy';
        $services = [];

        // Application status
        $services['application'] = [
            'status' => 'healthy',
            'version' => config('app.version', '1.0.0-foundation'),
            'environment' => config('app.env'),
        ];

        // Database connectivity
        try {
            DB::connection()->getPdo();
            $services['database'] = [
                'status' => 'healthy',
                'driver' => config('database.default'),
            ];
        } catch (Throwable $e) {
            $status = 'unhealthy';
            $services['database'] = [
                'status' => 'unhealthy',
                'error' => 'Database connection failed',
            ];
            Log::warning('Health check: Database connection failed', ['exception' => $e->getMessage()]);
        }

        // Redis connectivity
        try {
            $redisConnection = Redis::connection();
            $ping = $redisConnection->ping();
            $pingString = (string) $ping;
            $isRedisHealthy = ($ping === true || $pingString === 'PONG' || $pingString === '+PONG');

            $services['redis'] = [
                'status' => $isRedisHealthy ? 'healthy' : 'unhealthy',
            ];

            if (! $isRedisHealthy) {
                $status = 'unhealthy';
            }
        } catch (Throwable $e) {
            $status = 'unhealthy';
            $services['redis'] = [
                'status' => 'unhealthy',
                'error' => 'Redis connection failed',
            ];
            Log::warning('Health check: Redis connection failed', ['exception' => $e->getMessage()]);
        }

        $httpCode = $status === 'healthy' ? 200 : 503;

        return response()->json([
            'status' => $status,
            'timestamp' => now()->toIso8601String(),
            'services' => $services,
        ], $httpCode);
    }
}
