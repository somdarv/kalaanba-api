<?php

declare(strict_types=1);

namespace Kalaanba\Modules\PlayerAffiliation\Domain;

/**
 * Player claim status (engine doc §4). Ghost players are manager-created
 * placeholders (deferred WP); self-service profile creation always yields a
 * Claimed player controlling their own profile.
 */
enum PlayerClaimStatus: string
{
    case Ghost = 'ghost';
    case Claimed = 'claimed';
}
