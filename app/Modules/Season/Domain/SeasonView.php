<?php

declare(strict_types=1);

namespace Kalaanba\Modules\Season\Domain;

use DateTimeImmutable;

/**
 * Read-only projection of a season — what other engines and the API
 * surface need to know. Persisted shape lives in the `seasons` table.
 *
 * Engine doc: docs/engines/season/Season_Engine_UPDATED.md §2, §12.
 */
final readonly class SeasonView
{
    /**
     * @param  array<string, string>  $keyDates  ISO-8601 timestamps for cutoffs (challenge_new_cutoff_at, …).
     */
    public function __construct(
        public string $id,
        public string $code,
        public DateTimeImmutable $startsAt,
        public DateTimeImmutable $endsAt,
        public DateTimeImmutable $participationCutoffAt,
        public SeasonPhase $phase,
        public array $keyDates,
        public ?DateTimeImmutable $archivedAt,
    ) {}
}
