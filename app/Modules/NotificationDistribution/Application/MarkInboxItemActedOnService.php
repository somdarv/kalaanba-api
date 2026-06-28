<?php

declare(strict_types=1);

namespace Kalaanba\Modules\NotificationDistribution\Application;

use Kalaanba\Modules\NotificationDistribution\Domain\InboxRepository;

/**
 * Use case: mark an inbox row acted_on on behalf of its owner. Idempotent —
 * a no-op when the row is already acted_on / expired / cancelled.
 */
final class MarkInboxItemActedOnService
{
    public function __construct(private readonly InboxRepository $repository) {}

    public function handle(string $id, string $recipientUserId): bool
    {
        return $this->repository->markActedOn($id, $recipientUserId);
    }
}
