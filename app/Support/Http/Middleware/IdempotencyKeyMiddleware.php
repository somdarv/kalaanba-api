<?php

declare(strict_types=1);

namespace Kalaanba\Support\Http\Middleware;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

/**
 * Idempotency-Key middleware.
 *
 * Enforces engineering-standards §4 + §7: every state-mutating write MUST
 * carry an `Idempotency-Key` header. Duplicate keys (within the TTL window)
 * replay the original status code so retried mobile requests never produce
 * a second write.
 *
 * Cache contract:
 *  - Driver: any Laravel cache store (Redis in production, array in tests).
 *  - Key:    `kx:idem:v1:{user-or-ip}:{key}`
 *  - TTL:    24 hours (constitution §4 — mobile retries can be late).
 *  - Value:  the integer HTTP status produced by the original write.
 */
final class IdempotencyKeyMiddleware
{
    private const int TTL_SECONDS = 86_400;

    private const string CACHE_PREFIX = 'kx:idem:v1:';

    private const string HEADER = 'Idempotency-Key';

    private const string ERROR_CODE_MISSING = 'auth.idempotency_key_required';

    /** @var array<int, string> */
    private const array MUTATING_METHODS = ['POST', 'PUT', 'PATCH', 'DELETE'];

    public function handle(Request $request, Closure $next): Response
    {
        if (! in_array($request->method(), self::MUTATING_METHODS, true)) {
            return $next($request);
        }

        $key = $request->header(self::HEADER);

        if (! is_string($key) || trim($key) === '') {
            return $this->missingKeyResponse($request);
        }

        $cacheKey = self::CACHE_PREFIX.$this->actorKey($request).':'.$key;
        $existingStatus = Cache::get($cacheKey);

        if (is_int($existingStatus)) {
            return new JsonResponse([
                'data' => null,
                'meta' => [
                    'request_id' => (string) $request->header('X-Request-Id', ''),
                    'api_version' => 'v1',
                    'idempotent_replay' => true,
                ],
            ], $existingStatus);
        }

        $response = $next($request);

        if ($response->getStatusCode() < 500) {
            Cache::put($cacheKey, $response->getStatusCode(), self::TTL_SECONDS);
        }

        return $response;
    }

    private function actorKey(Request $request): string
    {
        $userId = $request->user()?->getAuthIdentifier();

        return $userId !== null ? "u:{$userId}" : 'ip:'.($request->ip() ?? 'unknown');
    }

    private function missingKeyResponse(Request $request): JsonResponse
    {
        return new JsonResponse([
            'error' => [
                'code' => self::ERROR_CODE_MISSING,
                'message' => 'Idempotency-Key header is required on write requests.',
                'details' => ['method' => $request->method()],
                'request_id' => (string) $request->header('X-Request-Id', ''),
            ],
        ], 400);
    }
}
