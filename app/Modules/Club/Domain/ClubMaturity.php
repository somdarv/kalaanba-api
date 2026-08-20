<?php

declare(strict_types=1);

namespace Kalaanba\Modules\Club\Domain;

/**
 * Club maturity levels (engine doc §4). Stable state machine — a club grows
 * without losing history. Publicly only "Verified Club" shows; the level is
 * stored internally. Self-service creation starts Informal.
 */
enum ClubMaturity: string
{
    case Informal = 'informal';
    case Structured = 'structured';
    case Verified = 'verified';
    case Registered = 'registered';
}
