<?php

declare(strict_types=1);

namespace Kalaanba\Support\Auth;

/**
 * Platform-level user role.
 *
 * Cross-cutting Support enum consumed by Policies, scope middleware, and engine
 * Application services. Engine modules MUST depend on the backing value
 * (snake_case stable key), never on the case name.
 *
 * Source of truth for the role catalogue:
 *  - docs/Architecture/Build_Plan.md §0.6
 *  - docs/engines/admin-governance/Admin_Configuration_Governance_Engine_System_Document.md §8
 */
enum Role: string
{
    // Universal default — every freshly registered actor starts here. It is
    // the account-level floor and is never surfaced as a public badge
    // (Identity Engine doc §9). The only path to a higher role is an audited
    // admin assignment.
    case User = 'user';

    // Public / unverified actors
    case Fan = 'fan';
    case Player = 'player';

    // Club-bound roles
    case ClubRep = 'club_rep';
    case ClubAdmin = 'club_admin';

    // Match-operations roles
    case CompetitionOrganizer = 'comp_org';
    case Referee = 'referee';
    case Officiator = 'officiator';
    case FacilityManager = 'facility_mgr';

    // Platform administration
    case HubAdmin = 'hub_admin';
    case KalaanbaAdmin = 'kalaanba_admin';
    case SuperAdmin = 'super_admin';

    /**
     * Default role assigned to a freshly registered actor before any
     * affiliation or clearance has been recorded.
     */
    public static function default(): self
    {
        return self::User;
    }

    /**
     * Roles permitted to bypass scope middleware. Used by policies that need
     * platform-wide reach (e.g. Super Admin overrides, KX admin audits).
     */
    public function isPlatformAdmin(): bool
    {
        return match ($this) {
            self::HubAdmin, self::KalaanbaAdmin, self::SuperAdmin => true,
            default => false,
        };
    }

    /**
     * The Super Admin role is the sole owner of cross-platform read access
     * to the admin audit log and irreversible governance overrides.
     */
    public function isSuperAdmin(): bool
    {
        return $this === self::SuperAdmin;
    }
}
