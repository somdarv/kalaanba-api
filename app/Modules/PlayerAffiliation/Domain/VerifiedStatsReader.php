<?php

declare(strict_types=1);

namespace Kalaanba\Modules\PlayerAffiliation\Domain;

/**
 * The only way this engine can learn a player's match record.
 *
 * A consumer-defined port: Player & Affiliation states what it NEEDS, and the
 * engine that owns match truth supplies it. Match / Fixture has no module yet,
 * so defining the port here rather than importing one from there keeps this
 * engine buildable without a dependency on something that does not exist.
 *
 * This interface is what makes engine doc §13 structural instead of a
 * convention. Player & Affiliation has no query, no counter and no arithmetic
 * that could produce an appearance: it can only ask. Until a Match adapter is
 * bound, the honest answer is `VerifiedRecord::empty()`, and that is exactly
 * what the default binding returns.
 *
 * The implementation is responsible for the Trust gate (Constitution Law 7):
 * only matches with `result_confirmed = true` AND clearance recorded may be
 * counted. Nothing downstream re-checks it, so an adapter that forgets is a
 * bug in the adapter.
 */
interface VerifiedStatsReader
{
    public function recordFor(string $playerId): VerifiedRecord;

    /**
     * Matches counted toward card confidence (§14). Separate from
     * `recordFor()->appearances` because an appearance is a stat and a
     * confirmed match is a gate — they are the same number today and will not
     * stay that way once substitutions and abandoned fixtures land.
     */
    public function confirmedMatchCountFor(string $playerId): int;
}
