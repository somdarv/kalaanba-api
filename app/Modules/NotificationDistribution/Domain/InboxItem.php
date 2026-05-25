<?php

declare(strict_types=1);

namespace Kalaanba\Modules\NotificationDistribution\Domain;

use DateTimeImmutable;

/**
 * Read DTO returned by the repository. Plain PHP — no Eloquent leak.
 */
final readonly class InboxItem
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function __construct(
        public string $id,
        public int $recipientUserId,
        public string $templateKey,
        public InboxCategory $category,
        public InboxUrgency $urgency,
        public InboxStatus $status,
        public string $title,
        public string $body,
        public ?string $actionUrl,
        public string $sourceType,
        public ?string $sourceId,
        public array $payload,
        public DateTimeImmutable $createdAt,
        public ?DateTimeImmutable $seenAt,
        public ?DateTimeImmutable $actedOnAt,
        public ?DateTimeImmutable $expiresAt,
    ) {
    }
}
