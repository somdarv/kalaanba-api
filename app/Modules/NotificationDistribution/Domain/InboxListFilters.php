<?php

declare(strict_types=1);

namespace Kalaanba\Modules\NotificationDistribution\Domain;

/**
 * Filters accepted by the inbox listing query. All optional — null means
 * "no filter on this field".
 */
final readonly class InboxListFilters
{
    public function __construct(
        public ?InboxStatus $status = null,
        public ?InboxCategory $category = null,
    ) {}
}
