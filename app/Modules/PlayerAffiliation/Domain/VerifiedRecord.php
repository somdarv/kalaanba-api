<?php

declare(strict_types=1);

namespace Kalaanba\Modules\PlayerAffiliation\Domain;

/**
 * A player's VERIFIED match record (engine doc §13).
 *
 * §13 is a locked rule: official player stats come only from confirmed match
 * records, and no claimed stat appears in a profile total. Every counter here
 * is therefore sourced from matches where `result_confirmed = true` with Trust
 * clearance recorded (Constitution Law 7).
 *
 * There is deliberately no mutator and no arithmetic on this class. It is a
 * carrier for numbers another engine computed, not a place to compute them:
 * Player & Affiliation does not own match truth (Law 1) and must not be able
 * to increment an appearance.
 */
final readonly class VerifiedRecord
{
    public function __construct(
        public int $appearances,
        public int $goals,
        public int $assists,
        public int $minutes,
        public int $yellowCards,
        public int $redCards,
    ) {}

    /**
     * The record of a player with nothing confirmed yet.
     *
     * Zeros rather than nulls, and never omitted from a response. A client that
     * has to tell "no stats yet" apart from "field missing" will guess, and a
     * guess about a stat line is exactly the claimed figure §13 forbids.
     */
    public static function empty(): self
    {
        return new self(0, 0, 0, 0, 0, 0);
    }
}
