<?php

declare(strict_types=1);

namespace Kalaanba\Modules\Club\Application;

use RuntimeException;

/**
 * The actor does not hold an admin-level membership of this club (engine doc
 * §7). Maps to 403.
 */
final class ClubNotYours extends RuntimeException
{
    public function __construct(public readonly string $clubId)
    {
        parent::__construct(sprintf('You do not administer club %s.', $clubId));
    }
}
