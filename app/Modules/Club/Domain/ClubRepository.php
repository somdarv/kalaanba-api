<?php

declare(strict_types=1);

namespace Kalaanba\Modules\Club\Domain;

/**
 * Write port for club identity + membership. The only place Eloquent touches
 * clubs lives behind this interface (Constitution §3 layering).
 */
interface ClubRepository
{
    public function create(Club $club): Club;

    public function addMembership(ClubMembership $membership): ClubMembership;

    /**
     * Point a club at a new crest (engine doc §5 step 6, §11 "Versioned").
     *
     * §11 files the crest under fields whose OLD VALUE is preserved when they
     * change. This overwrites, because there is nowhere yet to preserve it to;
     * the version history table lands with the identity-versioning packet, and
     * this method is where it will hook in.
     */
    public function updateCrestUrl(string $clubId, string $crestUrl, \DateTimeImmutable $at): void;
}
