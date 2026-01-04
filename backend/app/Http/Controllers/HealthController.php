<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;

/**
 * =============================================================================
 * Health Check Controller
 * =============================================================================
 *
 * Provides health check endpoints for Kubernetes probes.
 *
 * Endpoints:
 * ----------
 * - GET /health  (Liveness)  - Is the application alive?
 * - GET /ready   (Readiness) - Can the application accept traffic?
 *
 * Kubernetes Probe Configuration:
 * -------------------------------
 * Liveness: Determines if the container should be restarted
 *   - Should be FAST (no external dependencies)
 *   - Returns 200 if app is running
 *   - Returns 503 if app is stuck/unresponsive
 *
 * Readiness: Determines if the pod can receive traffic
 *   - Checks all external dependencies (DB, Redis, etc.)
 *   - Returns 200 if ready to accept requests
 *   - Returns 503 if dependencies are unavailable
 *
 * Startup: Determines if the application has started
 *   - Uses Laravel's built-in /up endpoint
 *   - Allows longer startup time before liveness kicks in
 *
 * =============================================================================
 */
class HealthController extends Controller
{
    /**
     * Liveness check - Is the application alive?
     *
     * This endpoint should be FAST. It only checks if the PHP process
     * is running and can respond to requests. No external dependencies.
     *
     * Used by: Kubernetes liveness probe
     *
     * @return JsonResponse
     */
    public function health(): JsonResponse
    {
        return response()->json([
            'status' => 'healthy',
            'timestamp' => now()->toIso8601String(),
        ]);
    }

    /**
     * Readiness check - Can the application accept traffic?
     *
     * This endpoint checks all critical dependencies:
     * - Database connection
     * - Redis connection (cache, sessions, queues)
     *
     * If any dependency is unavailable, the pod is marked as not ready
     * and will be removed from the load balancer.
     *
     * Used by: Kubernetes readiness probe
     *
     * @return JsonResponse
     */
    public function ready(): JsonResponse
    {
        $checks = [];
        $allHealthy = true;

        // Check Database
        try {
            DB::connection()->getPdo();
            $checks['database'] = [
                'status' => 'healthy',
                'connection' => config('database.default'),
            ];
        } catch (\Exception $e) {
            $checks['database'] = [
                'status' => 'unhealthy',
                'error' => 'Connection failed',
            ];
            $allHealthy = false;
        }

        // Check Redis
        try {
            Redis::ping();
            $checks['redis'] = [
                'status' => 'healthy',
                'connection' => config('database.redis.default.host'),
            ];
        } catch (\Exception $e) {
            $checks['redis'] = [
                'status' => 'unhealthy',
                'error' => 'Connection failed',
            ];
            $allHealthy = false;
        }

        // Check Cache
        try {
            $cacheKey = 'health_check_' . uniqid();
            Cache::put($cacheKey, true, 10);
            $value = Cache::get($cacheKey);
            Cache::forget($cacheKey);

            if ($value === true) {
                $checks['cache'] = [
                    'status' => 'healthy',
                    'driver' => config('cache.default'),
                ];
            } else {
                throw new \Exception('Cache read/write failed');
            }
        } catch (\Exception $e) {
            $checks['cache'] = [
                'status' => 'unhealthy',
                'error' => 'Read/write failed',
            ];
            $allHealthy = false;
        }

        $status = $allHealthy ? 'ready' : 'not_ready';
        $statusCode = $allHealthy ? 200 : 503;

        return response()->json([
            'status' => $status,
            'timestamp' => now()->toIso8601String(),
            'checks' => $checks,
        ], $statusCode);
    }
}
