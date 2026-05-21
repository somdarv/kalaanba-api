<?php

declare(strict_types=1);

namespace Kalaanba\Support\Http\Middleware;

use App\Models\User;
use Closure;
use DateTimeImmutable;
use Illuminate\Http\Request;
use Kalaanba\Support\Audit\AdminAuditEntry;
use Kalaanba\Support\Audit\AdminAuditWriter;
use Kalaanba\Support\Audit\PayloadRedactor;
use Psr\Clock\ClockInterface;
use Ramsey\Uuid\Uuid;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * Auto-log every authenticated *mutating* request performed by a platform
 * admin into the append-only admin_audit_log table.
 *
 * Constitution Law 5 — every meaningful action is audited.
 * Engineering-standards §10 — no PII / secret values in logs.
 *
 * Mounted globally on the api stack; opts itself out for:
 *   - unauthenticated requests (no actor)
 *   - non-platform-admin actors (regular users do not generate audit rows)
 *   - safe HTTP methods (GET, HEAD, OPTIONS)
 *
 * Writes are best-effort — a logging failure MUST NOT break the user request.
 */
final class AdminAuditMiddleware
{
    private const MUTATING_METHODS = ['POST', 'PUT', 'PATCH', 'DELETE'];

    public function __construct(
        private readonly AdminAuditWriter $writer,
        private readonly PayloadRedactor $redactor,
        private readonly ClockInterface $clock,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        /** @var Response $response */
        $response = $next($request);

        if (! $this->shouldAudit($request)) {
            return $response;
        }

        try {
            $this->writer->write($this->buildEntry($request, $response));
        } catch (Throwable) {
            // Audit failures must never break the user request. Sentry will
            // pick up the underlying exception via the global handler.
        }

        return $response;
    }

    private function shouldAudit(Request $request): bool
    {
        if (! in_array($request->getMethod(), self::MUTATING_METHODS, true)) {
            return false;
        }

        $user = $request->user();
        if (! $user instanceof User) {
            return false;
        }

        return $user->role->isPlatformAdmin();
    }

    private function buildEntry(Request $request, Response $response): AdminAuditEntry
    {
        /** @var User $user */
        $user = $request->user();
        $route = $request->route();
        $routeName = is_object($route) && method_exists($route, 'getName')
            ? $route->getName()
            : null;

        $occurredAt = DateTimeImmutable::createFromInterface($this->clock->now());

        return new AdminAuditEntry(
            id: Uuid::uuid7()->toString(),
            actorId: (string) $user->getKey(),
            actorRole: $user->role->value,
            requestId: (string) ($request->headers->get('X-Request-Id') ?? Uuid::uuid7()->toString()),
            route: $routeName,
            method: $request->getMethod(),
            path: $request->path(),
            responseStatus: $response->getStatusCode(),
            payloadRedacted: $this->redactor->redact($request->all()),
            occurredAt: $occurredAt,
        );
    }
}
