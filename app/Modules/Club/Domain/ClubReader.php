<?php

declare(strict_types=1);

namespace Kalaanba\Modules\Club\Domain;

/**
 * Read port for club discovery (engine doc §6 location, §15 public profile).
 */
interface ClubReader
{
    public function findById(string $id): ?Club;

    /**
     * Non-archived clubs in an Area, newest-first, capped at $limit.
     *
     * @return list<Club>
     */
    public function listByArea(string $areaId, int $limit): array;

    /**
     * Whether the user holds an admin-level active membership (Owner /
     * Cofounder / Admin) of the club — the authority to accept join requests
     * (engine doc §7). The sanctioned cross-engine read for affiliation authz.
     */
    public function userIsClubAdmin(string $clubId, string $userId): bool;

    /**
     * Non-archived clubs the user administers (Owner / Cofounder / Admin),
     * newest-first — the clubs whose join requests they can decide.
     *
     * @return list<Club>
     */
    public function listAdminClubsForUser(string $userId): array;
}
