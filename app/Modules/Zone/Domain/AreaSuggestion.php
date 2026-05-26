<?php

declare(strict_types=1);

namespace Kalaanba\Modules\Zone\Domain;

use DateTimeImmutable;

/**
 * Readonly view of a user-submitted Area suggestion.
 *
 * Engine doc §5; Constitution §1.5 (every meaningful action audited).
 */
final readonly class AreaSuggestion
{
    public function __construct(
        public string $id,
        public string $cityHubId,
        public ?string $proposedZoneId,
        public string $proposedName,
        public ?string $note,
        public string $submittedByUserId,
        public AreaSuggestionStatus $status,
        public ?string $reviewedByUserId,
        public ?string $reviewNote,
        public ?string $resultingAreaId,
        public DateTimeImmutable $submittedAt,
        public ?DateTimeImmutable $reviewedAt,
    ) {}
}
