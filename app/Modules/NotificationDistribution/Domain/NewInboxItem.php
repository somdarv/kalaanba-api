<?php

declare(strict_types=1);

namespace Kalaanba\Modules\NotificationDistribution\Domain;

use DateTimeImmutable;

/**
 * Input DTO for creating a new inbox row. Domain-pure — no Eloquent, no HTTP.
 *
 * WP-1 callers: test factory only. WP-2 will introduce the outbox listener
 * that builds these from cross-engine events.
 */
final readonly class NewInboxItem
{
    /**
     * @param  array<string, mixed>  $payload  Free-form JSON metadata stored verbatim
     *                                         (template variables, deep-link hints).
     *                                         Must NEVER contain secrets/PII/OTPs.
     */
    public function __construct(
        public string $recipientUserId,
        public string $templateKey,
        public InboxCategory $category,
        public InboxUrgency $urgency,
        public string $title,
        public string $body,
        public string $sourceType,
        public ?string $sourceId = null,
        public ?string $actionUrl = null,
        public array $payload = [],
        public ?DateTimeImmutable $expiresAt = null,
    ) {}
}
