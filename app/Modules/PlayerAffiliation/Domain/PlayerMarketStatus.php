<?php

declare(strict_types=1);

namespace Kalaanba\Modules\PlayerAffiliation\Domain;

/**
 * Football-market status of a player, separate from claim status (engine
 * doc §4: a player can be claimed and a free agent at the same time). V1
 * creates every self-service player as a free agent; club affiliation moves
 * them to Affiliated (shipped separately).
 */
enum PlayerMarketStatus: string
{
    case FreeAgent = 'free_agent';
    case Affiliated = 'affiliated';
}
