<?php

declare(strict_types=1);

namespace Kalaanba\Modules\Club\Domain;

use DateTimeImmutable;

/**
 * Readonly view of a club identity (engine doc §2, §15). `cityHubId` /
 * `areaId` reference the Zone engine; `createdByUserId` references Identity —
 * both cross-engine links (§1.1). `clubType` is a stable key from the
 * `club.types` config set (§1.4).
 */
final readonly class Club
{
    public function __construct(
        public string $id,
        public string $name,
        public string $clubType,
        public ClubTier $tier,
        public string $cityHubId,
        public string $areaId,
        public ?string $crestUrl,
        public ClubMaturity $maturity,
        public ClubVerificationState $verificationState,
        public ?ClubVerificationSource $verificationSource,
        public string $createdByUserId,
        public DateTimeImmutable $createdAt,
    ) {}

    /**
     * Whether this club may be shown to someone who does not administer it
     * (ADR-0017). Delegates to the state so the rule is stated once.
     */
    public function isPubliclyVisible(): bool
    {
        return $this->verificationState->isPubliclyVisible();
    }
}
