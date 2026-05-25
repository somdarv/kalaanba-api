<?php

declare(strict_types=1);

namespace Kalaanba\Modules\NotificationDistribution\Domain;

/**
 * One page returned by InboxRepository::listForRecipient.
 */
final readonly class InboxPage
{
    /**
     * @param  list<InboxItem>  $items
     */
    public function __construct(
        public array $items,
        public ?InboxCursor $nextCursor,
    ) {
    }
}
