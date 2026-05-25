<?php

declare(strict_types=1);

namespace Kalaanba\Modules\NotificationDistribution\Domain;

/**
 * Source category of an inbox item. Stable internal keys — UI labels are
 * configurable. Ref: engine doc §11.
 */
enum InboxCategory: string
{
    case Match = 'match';
    case Challenge = 'challenge';
    case Trust = 'trust';
    case Referee = 'referee';
    case Competition = 'competition';
    case Rp = 'rp';
    case Player = 'player';
    case Admin = 'admin';
    case System = 'system';
}
