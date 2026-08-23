<?php

declare(strict_types=1);

namespace Kalaanba\Modules\Club\Application;

use RuntimeException;

/** No live club carries this id. Maps to 404 at the HTTP boundary. */
final class ClubNotFound extends RuntimeException
{
    public function __construct(public readonly string $clubId)
    {
        parent::__construct(sprintf('Club %s not found.', $clubId));
    }
}
