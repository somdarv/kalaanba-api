<?php

declare(strict_types=1);

namespace Kalaanba\Modules\Season\Domain;

use DateTimeImmutable;

/**
 * Concrete time window for a single platform season — computed by
 * `SeasonCalendar` from `SeasonConfig`. All timestamps are UTC.
 *
 * Engine doc: docs/engines/season/Season_Engine_UPDATED.md §2 + §9.
 */
final readonly class SeasonWindow
{
    public function __construct(
        public string $code,
        public DateTimeImmutable $startsAt,
        public DateTimeImmutable $endsAt,
        public DateTimeImmutable $participationCutoffAt,
        public DateTimeImmutable $newRankedChallengeCutoffAt,
        public DateTimeImmutable $rankedAcceptanceCutoffAt,
        public DateTimeImmutable $closingWindowEndsAt,
        public DateTimeImmutable $archiveWindowEndsAt,
    ) {}

    /** @return array<string, string> */
    public function keyDates(): array
    {
        return [
            'participation_cutoff_at' => $this->participationCutoffAt->format(DATE_ATOM),
            'new_ranked_challenge_cutoff_at' => $this->newRankedChallengeCutoffAt->format(DATE_ATOM),
            'ranked_acceptance_cutoff_at' => $this->rankedAcceptanceCutoffAt->format(DATE_ATOM),
            'closing_window_ends_at' => $this->closingWindowEndsAt->format(DATE_ATOM),
            'archive_window_ends_at' => $this->archiveWindowEndsAt->format(DATE_ATOM),
        ];
    }
}
