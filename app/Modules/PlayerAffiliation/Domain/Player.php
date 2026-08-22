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

    /**
     * A copy carrying an owner's profile edits (engine doc §6).
     *
     * Only the fields `PATCH /players/{id}` accepts can move. `marketStatus`,
     * `claimStatus` and `id` are absent by construction, not by convention:
     * market status follows affiliation (§8), claim status follows the claim
     * flow (§4), and neither is the player's to set. A caller that wants to
     * change one cannot express it through this method, which is the point.
     *
     * `null` means "clear it" for the two nullable fields, so callers pass the
     * already-resolved value rather than a sentinel. Deciding whether an absent
     * key means "leave alone" belongs to the request layer, where the raw
     * payload still exists.
     */
    public function withProfile(
        string $firstName,
        string $lastName,
        string $stageName,
        ?int $preferredNumber,
        ?string $primaryPosition,
        PlayerAvailability $availability,
        ?string $headshotUrl,
    ): self {
        return new self(
            id: $this->id,
            userId: $this->userId,
            firstName: $firstName,
            lastName: $lastName,
            stageName: $stageName,
            preferredNumber: $preferredNumber,
            primaryPosition: $primaryPosition,
            availability: $availability,
            marketStatus: $this->marketStatus,
            claimStatus: $this->claimStatus,
            headshotUrl: $headshotUrl,
            createdAt: $this->createdAt,
        );
    }

    /**
     * A copy pointing at a newly stored photo (engine doc §7).
     *
     * Narrower than {@see withProfile} on purpose. An upload changes exactly
     * one field, and routing it through the wide method would mean the media
     * use case had to restate the player's name, number and availability to
     * change a URL — six chances to write back a stale value read a moment
     * earlier.
     */
    public function withHeadshotUrl(?string $headshotUrl): self
    {
        return new self(
            id: $this->id,
            userId: $this->userId,
            firstName: $this->firstName,
            lastName: $this->lastName,
            stageName: $this->stageName,
            preferredNumber: $this->preferredNumber,
            primaryPosition: $this->primaryPosition,
            availability: $this->availability,
            marketStatus: $this->marketStatus,
            claimStatus: $this->claimStatus,
            headshotUrl: $headshotUrl,
            createdAt: $this->createdAt,
        );
    }
}
