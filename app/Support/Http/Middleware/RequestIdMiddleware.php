<?php

declare(strict_types=1);

namespace Kalaanba\Support\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Ramsey\Uuid\Uuid;
use Sentry\SentrySdk;
use Sentry\State\Scope;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

use function Sentry\configureScope;

/**
 * Generate or accept an `X-Request-Id` header on every API request, propagate
 * it into the Log context + Sentry scope, and echo it back on the response.
 *
 * - Format: lowercase UUIDv4 unless the incoming header already provides one
 *   (we trim & length-check to 64 chars; anything else is replaced).
 * - This is what makes structured logs, Sentry events, and audit rows joinable
 *   from one HTTP call across the stack (engineering-standards §10).
 * - Best-effort: Sentry scope writes are wrapped in try/catch — if Sentry is
 *   not configured (no DSN) we silently no-op.
 */
final class RequestIdMiddleware
{
    private const HEADER = 'X-Request-Id';

    private const MAX_LENGTH = 64;

    public function handle(Request $request, Closure $next): Response
    {
        $requestId = $this->resolveRequestId($request);

        $request->headers->set(self::HEADER, $requestId);
        $request->attributes->set('request_id', $requestId);

        Log::shareContext(['request_id' => $requestId]);

        $this->tagSentry($requestId);

        $response = $next($request);

        if (! $response->headers->has(self::HEADER)) {
            $response->headers->set(self::HEADER, $requestId);
        }

        return $response;
    }

    private function resolveRequestId(Request $request): string
    {
        $incoming = $request->headers->get(self::HEADER);

        if (is_string($incoming)) {
            $incoming = trim($incoming);

            if ($incoming !== '' && strlen($incoming) <= self::MAX_LENGTH) {
                return $incoming;
            }
        }

        return Uuid::uuid4()->toString();
    }

    private function tagSentry(string $requestId): void
    {
        try {
            if (! class_exists(SentrySdk::class)) {
                return;
            }

            configureScope(function (Scope $scope) use ($requestId): void {
                $scope->setTag('request_id', $requestId);
            });
        } catch (Throwable) {
            // Sentry not booted / no DSN — fine.
        }
    }
}
