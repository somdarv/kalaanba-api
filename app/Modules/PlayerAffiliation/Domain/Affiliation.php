<?php

declare(strict_types=1);

namespace Kalaanba\Modules\PlayerAffiliation\Domain;

use DateTimeImmutable;

/**
 * Readonly view of a player↔club affiliation (engine doc §8). `clubId`
 * references the Club engine (cross-engine, no FK). Consent flow: a player
 * requests; a club Owner/Admin decides (§11).
 */
final readonly class Affiliation
{
    public function __construct(
        public string $id,
        public string $playerId,
        public string $clubId,
        public AffiliationState $state,
        public string $requestedByUserId,
        public ?string $decidedByUserId,
        public DateTimeImmutable $requestedAt,
        public ?DateTimeImmutable $decidedAt,
    ) {}
}
