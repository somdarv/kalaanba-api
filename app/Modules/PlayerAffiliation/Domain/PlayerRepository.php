<?php

declare(strict_types=1);

namespace Kalaanba\Modules\PlayerAffiliation\Domain;

/**
 * Write/read port for player identities. The only place Eloquent touches
 * players lives behind this interface (Constitution §3 layering).
 */
interface PlayerRepository
{
    /** Non-archived player owned by this user, or null. */
    public function findByUserId(string $userId): ?Player;

    public function findById(string $id): ?Player;

    public function create(Player $player): Player;
}
