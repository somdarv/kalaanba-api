<?php

declare(strict_types=1);

namespace Kalaanba\Modules\Club\Domain;

/**
 * How a club's verification was arrived at (engine doc §4's locked rule, §10's
 * three upgrade paths). Stable internal keys, stored internally only.
 *
 * The public surface shows one badge, "Verified Club", and never the source
 * (§4). Which path a club took is an internal detail: publishing it would rank
 * clubs by how they were checked, which is not a ranking Kalaanba makes.
 */
enum ClubVerificationSource: string
{
    /** Complete profile, active admins, verified matches, clean reliability. */
    case PlatformHistory = 'platform_history';

    /** Registration document or institution letter, plus a second contact. */
    case Documents = 'documents';

    /** Known contact, local reference, manual review. Pilot clubs. */
    case AdminFastTrack = 'admin_fast_track';
}
