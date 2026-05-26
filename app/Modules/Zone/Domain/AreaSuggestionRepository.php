<?php

declare(strict_types=1);

namespace Kalaanba\Modules\Zone\Domain;

use DateTimeImmutable;

/**
 * Write-side port for the Area suggestion workflow.
 *
 * Engine doc §5: users suggest, admins approve/reject. Append-only with
 * status transitions (Constitution §1.13).
 */
interface AreaSuggestionRepository
{
    public function findById(string $id): ?AreaSuggestion;

    public function submit(AreaSuggestion $suggestion): AreaSuggestion;

    public function markApproved(
        string $suggestionId,
        string $reviewerUserId,
        string $resultingAreaId,
        ?string $reviewNote,
        DateTimeImmutable $reviewedAt,
    ): AreaSuggestion;

    public function markRejected(
        string $suggestionId,
        string $reviewerUserId,
        ?string $reviewNote,
        DateTimeImmutable $reviewedAt,
    ): AreaSuggestion;

    public function promoteToArea(
        string $suggestionId,
        string $zoneId,
        string $name,
    ): Area;
}
