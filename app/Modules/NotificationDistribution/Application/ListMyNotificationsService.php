<?php

declare(strict_types=1);

namespace Kalaanba\Modules\NotificationDistribution\Application;

use Kalaanba\Modules\NotificationDistribution\Domain\InboxCursor;
use Kalaanba\Modules\NotificationDistribution\Domain\InboxListFilters;
use Kalaanba\Modules\NotificationDistribution\Domain\InboxPage;
use Kalaanba\Modules\NotificationDistribution\Domain\InboxRepository;

/**
 * Use case: list a user's inbox. Thin orchestration over the repository —
 * cursor decoding and limit clamping are done by the caller (controller +
 * Form Request) so this service remains framework-agnostic.
 */
final class ListMyNotificationsService
{
    public function __construct(private readonly InboxRepository $repository) {}

    public function handle(
        string $recipientUserId,
        InboxListFilters $filters,
        ?InboxCursor $cursor,
        int $limit,
    ): InboxPage {
        return $this->repository->listForRecipient(
            $recipientUserId,
            $filters,
            $cursor,
            $limit,
        );
    }
}
