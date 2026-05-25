<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Throwable;

/**
 * Lightweight liveness/readiness probe for uptime monitors
 * (BetterStack / UptimeRobot / k8s) and platform smoke tests.
 *
 * Returns 200 when DB + Redis respond, 503 when either dependency is down.
 * No auth, no rate-limit beyond the global throttle — must stay extremely cheap.
 */
final class HealthController
{
    public function __invoke(): JsonResponse
    {
        $checks = [
            'database' => $this->checkDatabase(),
            'redis' => $this->checkRedis(),
        ];

        $allOk = collect($checks)->every(fn (array $c): bool => $c['ok']);

        return new JsonResponse([
            'status' => $allOk ? 'ok' : 'degraded',
            'service' => 'kalaanba-api',
            'environment' => app()->environment(),
            'time' => Carbon::now('UTC')->toIso8601String(),
            'checks' => $checks,
        ], $allOk ? 200 : 503);
    }

    /**
     * @return array{ok: bool, latency_ms: int|null, error?: string}
     */
    private function checkDatabase(): array
    {
        $start = microtime(true);

        try {
            DB::connection()->getPdo()->query('SELECT 1');

            return [
                'ok' => true,
                'latency_ms' => $this->elapsedMs($start),
            ];
        } catch (Throwable $e) {
            return [
                'ok' => false,
                'latency_ms' => $this->elapsedMs($start),
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * @return array{ok: bool, latency_ms: int|null, error?: string}
     */
    private function checkRedis(): array
    {
        $start = microtime(true);

        try {
            $response = Redis::connection()->command('ping');

            $ok = $response === true
                || (is_string($response) && strtoupper($response) === 'PONG')
                || (is_object($response) && strtoupper((string) $response) === 'PONG');

            return [
                'ok' => $ok,
                'latency_ms' => $this->elapsedMs($start),
            ];
        } catch (Throwable $e) {
            return [
                'ok' => false,
                'latency_ms' => $this->elapsedMs($start),
                'error' => $e->getMessage(),
            ];
        }
    }

    private function elapsedMs(float $start): int
    {
        return (int) round((microtime(true) - $start) * 1000);
    }
}
