<?php

declare(strict_types=1);

namespace Kalaanba\Modules\PlayerAffiliation\Domain;

/**
 * Player↔club affiliation states (engine doc §8). Stable internal keys.
 * V1 uses Requested → Active (accept) / Declined (reject); the remaining
 * states are defined for the full lifecycle (transfers, loans — deferred).
 */
enum AffiliationState: string
{
    case Invited = 'invited';
    case Requested = 'requested';
    case Active = 'active';
    case Inactive = 'inactive';
    case Trialist = 'trialist';
    case OnLoan = 'on_loan';
    case Released = 'released';
    case Banned = 'banned';
    case Declined = 'declined';
    case Removed = 'removed';
}
