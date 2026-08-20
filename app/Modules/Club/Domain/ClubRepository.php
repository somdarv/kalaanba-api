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
}
