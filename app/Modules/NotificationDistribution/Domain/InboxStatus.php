<?php

declare(strict_types=1);

namespace Kalaanba\Modules\NotificationDistribution\Domain;

/**
 * Lifecycle status of an in-app inbox item.
 *
 * Ref: docs/engines/notification-distribution/Notification_Distribution_Engine_System_Document.md §13.
 */
enum InboxStatus: string
{
    case Created = 'created';
    case Queued = 'queued';
    case Sent = 'sent';
    case Delivered = 'delivered';
    case Seen = 'seen';
    case ActedOn = 'acted_on';
    case Expired = 'expired';
    case Cancelled = 'cancelled';
    case Failed = 'failed';

    /**
     * A status is terminal when no further user-driven transition is allowed.
     * Mark-seen / mark-acted-on against a terminal row is a no-op (204).
     */
    public function isTerminal(): bool
    {
        return match ($this) {
            self::Seen, self::ActedOn, self::Expired, self::Cancelled => true,
            default => false,
        };
    }

    /**
     * Statuses that count toward the unread badge. Per engine doc §13 once a
     * row is seen/acted-on/expired/cancelled the badge should drop it.
     */
    public function countsAsUnread(): bool
    {
        return ! in_array(
            $this,
            [self::Seen, self::ActedOn, self::Expired, self::Cancelled],
            true,
        );
    }
}
