<?php

declare(strict_types=1);

namespace Kalaanba\Support\Audit;

use DateTimeImmutable;

/**
 * Immutable audit-log row, produced by AdminAuditMiddleware and consumed by
 * the AdminAuditWriter port. Never reaches the HTTP layer directly — the read
 * endpoint returns its own resource shape.
 */
final readonly class AdminAuditEntry
{
    /**
     * @param  array<string,mixed>  $payloadRedacted
     */
    public function __construct(
        public string $id,
        public string $actorId,
        public string $actorRole,
        public string $requestId,
        public ?string $route,
        public string $method,
        public string $path,
        public int $responseStatus,
        public array $payloadRedacted,
        public DateTimeImmutable $occurredAt,
    ) {}
}
