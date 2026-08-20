<?php

declare(strict_types=1);

namespace Kalaanba\Modules\PlayerAffiliation\Domain;

use DateTimeImmutable;

/**
 * Readonly view of a player football identity (engine doc §3, §4, §6).
 *
 * The name model (first / last / stage) is the player's own — distinct from
 * the Identity account `name` (different engine, §1.1). `preferred_number` and
 * `primaryPosition` are optional preferences; `primaryPosition` is a stable
 * internal key drawn from the `player.positions` config set (§1.4).
 */
final readonly class Player
{
    public function __construct(
        public string $id,
        public string $userId,
        public string $firstName,
        public string $lastName,
        public string $stageName,
        public ?int $preferredNumber,
        public ?string $primaryPosition,
        public PlayerAvailability $availability,
        public PlayerMarketStatus $marketStatus,
        public PlayerClaimStatus $claimStatus,
        public ?string $headshotUrl,
        public DateTimeImmutable $createdAt,
    ) {}
}
