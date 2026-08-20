<?php

declare(strict_types=1);

namespace Kalaanba\Modules\PlayerAffiliation\Domain;

use DateTimeImmutable;

/**
 * Write/read port for player↔club affiliations. The only place Eloquent
 * touches affiliations lives behind this interface (Constitution §3 layering).
 */
interface AffiliationRepository
{
    public function findById(string $id): ?Affiliation;

    public function findByPlayerAndClub(string $playerId, string $clubId): ?Affiliation;

    public function create(Affiliation $affiliation): Affiliation;

    public function transitionState(
        string $affiliationId,
        AffiliationState $state,
        string $decidedByUserId,
        DateTimeImmutable $decidedAt,
    ): Affiliation;

    /**
     * Pending (requested) affiliations for a club, newest-first.
     *
     * @return list<Affiliation>
     */
    public function listPendingForClub(string $clubId): array;
}
