<?php

declare(strict_types=1);

namespace Kalaanba\Modules\NotificationDistribution\Domain;

/**
 * Urgency drives channel selection and reminder cadence downstream
 * (WhatsApp lands in Stage 4). Ref: engine doc §10.
 */
enum InboxUrgency: string
{
    case Info = 'info';
    case Normal = 'normal';
    case Important = 'important';
    case Urgent = 'urgent';
    case Critical = 'critical';
}
