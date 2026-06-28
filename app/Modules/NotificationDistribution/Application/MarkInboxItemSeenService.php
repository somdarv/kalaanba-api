<?php

declare(strict_types=1);

namespace Kalaanba\Modules\NotificationDistribution\Application;

use Kalaanba\Modules\NotificationDistribution\Domain\InboxRepository;

/**
 * Use case: mark an inbox row seen on behalf of its owner. Returns true when
 * a real status transition occurred; false when the row was already in a
 * terminal status (still a successful idempotent call from the API's POV).
 */
final class MarkInboxItemSeenService
{
    public function __construct(private readonly InboxRepository $repository) {}

    public function handle(string $id, string $recipientUserId): bool
    {
        return $this->repository->markSeen($id, $recipientUserId);
    }
}
