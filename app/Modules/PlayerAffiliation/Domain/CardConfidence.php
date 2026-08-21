<?php

declare(strict_types=1);

namespace Kalaanba\Modules\PlayerAffiliation\Domain;

/**
 * How much of a player's card is backed by confirmed football (engine doc §14).
 *
 * §14 rules a numeric player rating out of V1 — a rating without minutes, role,
 * opposition strength and defensive context goes unfair fast — and puts a
 * confidence LABEL in its place. This is that label, plus the two numbers a
 * client needs to show progress without deriving anything.
 *
 * `tier` and `nextTier` are stable internal keys (Law 4). The display strings
 * live in `player.card_confidence.labels` and reach clients through the meta
 * endpoint, never through this object.
 *
 * `matchesToNextTier` is resolved here rather than in the client on purpose:
 * the thresholds are effective-dated config, so only the server can know which
 * ones applied to a given card at a given time.
 */
final readonly class CardConfidence
{
    public function __construct(
        public string $tier,
        public int $confirmedMatches,
        public ?string $nextTier,
        public ?int $matchesToNextTier,
    ) {}
}
