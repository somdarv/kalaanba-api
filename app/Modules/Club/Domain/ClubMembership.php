<?php

declare(strict_types=1);

namespace Kalaanba\Modules\Club\Domain;

use DateTimeImmutable;

/**
 * Readonly view of a club membership — the (user, club, role) relationship
 * that grants control of a club (engine doc §7). `userId` references Identity
 * (cross-engine, no FK).
 */
final readonly class ClubMembership
{
    public function __construct(
        public string $id,
        public string $clubId,
        public string $userId,
        public ClubRole $role,
        public string $state,
        public DateTimeImmutable $createdAt,
    ) {}
}
