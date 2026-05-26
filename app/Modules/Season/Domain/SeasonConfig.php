<?php

declare(strict_types=1);

namespace Kalaanba\Modules\Season\Domain;

/**
 * Configurable defaults for a season, sourced from Admin Config
 * (`season.*` keys). All values are integers — months/days — so the
 * calendar can be re-shaped without code changes (Constitution §1.2).
 *
 * Engine doc: docs/engines/season/Season_Engine_UPDATED.md §12.
 */
final readonly class SeasonConfig
{
    /**
     * @param  array<int>  $peakMonths  Months (1..12) considered "high activity peak". Engine doc §2.
     */
    public function __construct(
        public int $startMonth,
        public int $startDay,
        public int $endMonth,
        public int $endDay,
        public int $participationCutoffMonth,
        public int $participationCutoffDay,
        public int $closingWindowEndDay,
        public int $archiveWindowEndDay,
        public int $newRankedChallengeCutoffMonth,
        public int $newRankedChallengeCutoffDay,
        public int $rankedAcceptanceCutoffMonth,
        public int $rankedAcceptanceCutoffDay,
        public array $peakMonths,
    ) {}

    /**
     * Canonical defaults straight from the engine doc — used by tests and
     * as a fallback when Admin Config has not yet been seeded.
     */
    public static function defaults(): self
    {
        return new self(
            startMonth: 4,
            startDay: 1,
            endMonth: 2,
            endDay: 31,
            participationCutoffMonth: 7,
            participationCutoffDay: 31,
            closingWindowEndDay: 14,
            archiveWindowEndDay: 31,
            newRankedChallengeCutoffMonth: 2,
            newRankedChallengeCutoffDay: 7,
            rankedAcceptanceCutoffMonth: 2,
            rankedAcceptanceCutoffDay: 14,
            peakMonths: [12, 1],
        );
    }
}
