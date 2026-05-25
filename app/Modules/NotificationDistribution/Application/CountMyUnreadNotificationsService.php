<?php

declare(strict_types=1);

namespace Kalaanba\Modules\NotificationDistribution\Application;

use Kalaanba\Modules\NotificationDistribution\Domain\InboxRepository;

/**
 * Use case: count unread inbox items for the badge endpoint. Cap is supplied
 * by the caller (controller reads notification.inbox.unread_badge_cap from
 * Admin Configuration).
 */
final class CountMyUnreadNotificationsService
{
    public function __construct(private readonly InboxRepository $repository)
    {
    }

    /**
     * @return array{count: int, truncated: bool}
     */
    public function handle(int $recipientUserId, int $cap): array
    {
        return $this->repository->countUnread($recipientUserId, $cap);
    }
}
