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

    /**
     * Persist an owner's profile edits and return the stored row.
     *
     * Returns the re-read entity rather than the argument so the caller sees
     * what the database actually holds, not what it was asked to hold.
     */
    public function update(Player $player): Player;
}
