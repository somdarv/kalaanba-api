<?php

declare(strict_types=1);

namespace Kalaanba\Modules\PlayerAffiliation\Application;

use RuntimeException;

/**
 * Raised when the actor does not own the player they are trying to edit
 * (engine doc §17, player identity integrity). Nobody edits another player's
 * record through this route, not even a club admin — club-side changes go
 * through affiliation (§11). Mapped to HTTP 403.
 *
 * Deliberately distinct from `PlayerNotFound` at the application layer even
 * though the HTTP boundary may choose to blur them: the log line should say
 * which of the two actually happened.
 */
final class PlayerNotYours extends RuntimeException
{
    public function __construct(public readonly string $playerId)
    {
        parent::__construct("Player {$playerId} belongs to another user.");
    }
}
