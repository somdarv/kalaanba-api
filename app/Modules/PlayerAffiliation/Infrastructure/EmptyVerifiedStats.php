<?php

declare(strict_types=1);

namespace Kalaanba\Modules\PlayerAffiliation\Infrastructure;

use Kalaanba\Modules\PlayerAffiliation\Domain\VerifiedRecord;
use Kalaanba\Modules\PlayerAffiliation\Domain\VerifiedStatsReader;

/**
 * The default `VerifiedStatsReader`: nothing is confirmed, because nothing can
 * be yet.
 *
 * Match / Fixture has no endpoints, no module and no `result_confirmed` to
 * read, so an empty record is not a placeholder standing in for real data — it
 * is the accurate answer. Engine doc §13 gates stats behind confirmed matches;
 * with no confirmed matches in existence, every player's verified record is
 * genuinely zero.
 *
 * When the Match module lands, it binds its own adapter over this one in the
 * service provider and nothing else in this engine changes. That is the whole
 * point of the port.
 *
 * Deliberately NOT configurable and NOT seedable. A "demo stats" implementation
 * living behind this interface would put fabricated goals on a real player's
 * profile through the sanctioned path, which is the one thing §13 exists to
 * prevent. Demo data belongs in the frontend seed layer, clearly badged, and
 * nowhere near an engine.
 */
final class EmptyVerifiedStats implements VerifiedStatsReader
{
    public function recordFor(string $playerId): VerifiedRecord
    {
        return VerifiedRecord::empty();
    }

    public function confirmedMatchCountFor(string $playerId): int
    {
        return 0;
    }
}
