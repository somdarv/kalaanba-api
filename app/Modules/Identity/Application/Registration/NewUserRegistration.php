<?php

declare(strict_types=1);

namespace Kalaanba\Modules\Identity\Application\Registration;

use DateTimeImmutable;

/**
 * Persistence input for a newly created user row.
 *
 * Either `email` or `phoneE164Hash` MUST be non-null; both being null is
 * blocked by the DB CHECK constraint `users_channel_present_check` (engine
 * doc §8). Application validators enforce the same rule at the boundary
 * so we surface a 422 rather than a DB error.
 *
 * `passwordHash` may be null for phone-only signups.
 *
 * `claimedAt` is non-null when the user enters CLAIMED state at creation
 * (phone signup); null when the user enters PENDING_CLAIM (email signup
 * awaiting verification).
 */
final readonly class NewUserRegistration
{
    public function __construct(
        public string $id,
        public string $name,
        public ?string $areaId,
        public ?string $email,
        public ?string $phoneE164Hash,
        public ?string $passwordHash,
        public string $role,
        public ?DateTimeImmutable $claimedAt,
        public DateTimeImmutable $createdAt,
    ) {}
}
