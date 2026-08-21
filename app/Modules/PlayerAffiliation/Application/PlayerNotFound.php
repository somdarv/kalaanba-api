<?php

declare(strict_types=1);

namespace Kalaanba\Modules\PlayerAffiliation\Application;

use RuntimeException;

/**
 * Raised when no live player carries the given id. Archived players count as
 * missing here: Constitution Law 13 keeps the row for history, it does not keep
 * it editable. Mapped to HTTP 404.
 */
final class PlayerNotFound extends RuntimeException
{
    public function __construct(public readonly string $playerId)
    {
        parent::__construct("No live player with id {$playerId}.");
    }
}
