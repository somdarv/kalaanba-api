<?php

declare(strict_types=1);

namespace Kalaanba\Modules\Club\Domain;

/**
 * Where a club stands with verification (engine doc §9, §10; ADR-0017). Stable
 * internal keys.
 *
 * Distinct from {@see ClubMaturity}, which is a growth axis. Maturity says how
 * developed a club is; this says whether anyone has checked. A club can be
 * `registered` maturity and `pending` here, which is exactly the state an
 * unproven official claim sits in.
 */
enum ClubVerificationState: string
{
    /** A local club. Nothing to prove, nothing withheld. */
    case NotRequired = 'not_required';

    /**
     * An official claim awaiting the document review. The club exists and is
     * hidden from every public read until this changes. See
     * {@see self::isPubliclyVisible()}.
     */
    case Pending = 'pending';

    /** Reviewed and accepted. The §4 "Verified Club" badge becomes showable. */
    case Cleared = 'cleared';

    /**
     * Reviewed and refused. The club is archived alongside this (Law 13,
     * never deleted) and the name is released.
     */
    case Rejected = 'rejected';

    /**
     * Whether a club in this state may be shown to someone who does not
     * administer it.
     *
     * `pending` is the whole reason this enum exists: that club bears a name it
     * has not yet earned, so it must not reach discovery, a public profile, a
     * join request, or any feed. The one read that deliberately ignores this is
     * `listAdminClubsForUser`, so an Owner can see their own claim being looked
     * at rather than assume it vanished.
     */
    public function isPubliclyVisible(): bool
    {
        return $this !== self::Pending;
    }
}
