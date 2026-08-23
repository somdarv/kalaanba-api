<?php

declare(strict_types=1);

namespace Kalaanba\Modules\Club\Domain;

/**
 * The two doors at club creation (engine doc §5, ADR-0017). Stable internal
 * keys; the labels live in the `club.tiers.labels` config map.
 *
 * Tier is asked FIRST because everything after it depends on the answer: which
 * club types are offered, whether a name from `club.name.reserved_terms` is
 * refused or routed to review, and whether the club goes live on submit.
 *
 * The set is fixed here rather than read from config even though `club.tiers`
 * exists: config owns the ORDER and the LABELS, but each tier carries distinct
 * behaviour in this engine, and a third key appearing in config would have no
 * code path to run. Config decides how the two are presented, not that there
 * are two.
 */
enum ClubTier: string
{
    /** A community side, friend crew, school or work team. Goes live at once. */
    case Amateur = 'amateur';

    /** A registered club, academy or institution. Held until an admin clears it. */
    case Professional = 'professional';

    /**
     * Whether this tier may claim a name from `club.name.reserved_terms`.
     *
     * Only the official door may, and claiming one is precisely what the
     * document review in engine doc §10 exists to check.
     */
    public function mayClaimReservedName(): bool
    {
        return $this === self::Professional;
    }

    /** The state a club created through this door starts in (§4, §9). */
    public function initialVerificationState(): ClubVerificationState
    {
        return $this === self::Professional
            ? ClubVerificationState::Pending
            : ClubVerificationState::NotRequired;
    }

    /**
     * The maturity a club created through this door starts at (§4).
     *
     * An official claim starts at `registered` because that is what is being
     * claimed. It is not yet trusted: `verification_state` carries that, and
     * the public "Verified Club" badge reads clearance, never maturity.
     */
    public function initialMaturity(): ClubMaturity
    {
        return $this === self::Professional
            ? ClubMaturity::Registered
            : ClubMaturity::Informal;
    }
}
