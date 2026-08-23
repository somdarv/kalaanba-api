<?php

declare(strict_types=1);

namespace Kalaanba\Modules\Club\Application;

use RuntimeException;

/**
 * A `local` club tried to take a name that belongs to a real club (ADR-0017).
 *
 * Carries the canonical name it collided with so a refusal can be audited as
 * "someone tried to register Asante Kotoko" rather than as an anonymous 422.
 * The message is never shown to the user: the HTTP boundary maps this to the
 * stable code `club.name_reserved` and the client owns the copy (Law 4).
 */
final class ClubNameReserved extends RuntimeException
{
    public function __construct(
        public readonly string $attemptedName,
        public readonly string $reservedFor,
    ) {
        parent::__construct(
            sprintf('Club name "%s" is reserved for %s.', $attemptedName, $reservedFor)
        );
    }
}
