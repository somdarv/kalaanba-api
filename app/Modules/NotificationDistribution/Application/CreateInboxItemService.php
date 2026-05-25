<?php

declare(strict_types=1);

namespace Kalaanba\Modules\NotificationDistribution\Application;

use Kalaanba\Modules\NotificationDistribution\Domain\InboxRepository;
use Kalaanba\Modules\NotificationDistribution\Domain\NewInboxItem;

/**
 * Use case: write a new in-app inbox row.
 *
 * WP-1: invoked only from tests and (later) module-internal seeders. WP-2 will
 * add a Listener that calls this from outbox events emitted by other engines.
 * Cross-engine callers MUST go through that listener — never inject this
 * service into a foreign module (engine boundaries, Constitution Law 1).
 */
final class CreateInboxItemService
{
    public function __construct(private readonly InboxRepository $repository)
    {
    }

    public function handle(NewInboxItem $item): string
    {
        return $this->repository->insert($item);
    }
}
