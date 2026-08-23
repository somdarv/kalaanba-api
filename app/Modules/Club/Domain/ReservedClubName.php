<?php

declare(strict_types=1);

namespace Kalaanba\Modules\Club\Domain;

/**
 * One protected club name and the shorthands people use for it
 * (`club.name.reserved_terms`, ADR-0017 §3).
 *
 * The two fields are matched with deliberately different widths, and that
 * asymmetry is the decision rather than an implementation detail:
 *
 *   - `canonical` matches if it appears ANYWHERE in the candidate as whole
 *     tokens, because a full club name is specific enough that containing it is
 *     always a claim on that club. "Tamale Manchester United" is refused.
 *   - `aliases` match only if one IS the whole candidate, because a single word
 *     is not. "Kotoko" is the Twi word for porcupine and "Hearts" an ordinary
 *     English word; refusing "Kotoko Boys" to catch a dodge nobody has
 *     attempted would take a real Tamale side's own name away from it.
 *
 * The rule that follows: when in doubt, a word goes in `aliases`, never in a
 * `canonical` of its own.
 */
final readonly class ReservedClubName
{
    /**
     * @param  list<string>  $aliases
     */
    public function __construct(
        public string $canonical,
        public array $aliases = [],
    ) {}
}
