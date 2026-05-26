<?php

declare(strict_types=1);

namespace Kalaanba\Modules\Zone\Infrastructure\Eloquent;

use DateTimeImmutable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Kalaanba\Modules\Zone\Domain\Area;
use Kalaanba\Modules\Zone\Domain\AreaSuggestion;
use Kalaanba\Modules\Zone\Domain\AreaSuggestionRepository;
use Kalaanba\Modules\Zone\Domain\AreaSuggestionStatus;
use RuntimeException;

final class EloquentAreaSuggestionRepository implements AreaSuggestionRepository
{
    public function findById(string $id): ?AreaSuggestion
    {
        /** @var AreaSuggestionRecord|null $row */
        $row = AreaSuggestionRecord::query()->find($id);

        return $row === null ? null : $this->map($row);
    }

    public function submit(AreaSuggestion $suggestion): AreaSuggestion
    {
        $record = new AreaSuggestionRecord;
        $record->forceFill([
            'id' => $suggestion->id,
            'city_hub_id' => $suggestion->cityHubId,
            'proposed_zone_id' => $suggestion->proposedZoneId,
            'proposed_name' => $suggestion->proposedName,
            'note' => $suggestion->note,
            'submitted_by_user_id' => $suggestion->submittedByUserId,
            'status' => $suggestion->status->value,
            'submitted_at' => Carbon::instance($suggestion->submittedAt),
        ])->save();

        return $this->map($record->refresh());
    }

    public function markApproved(
        string $suggestionId,
        string $reviewerUserId,
        string $resultingAreaId,
        ?string $reviewNote,
        DateTimeImmutable $reviewedAt,
    ): AreaSuggestion {
        /** @var AreaSuggestionRecord $row */
        $row = AreaSuggestionRecord::query()->findOrFail($suggestionId);
        $row->forceFill([
            'status' => AreaSuggestionStatus::Approved->value,
            'reviewed_by_user_id' => $reviewerUserId,
            'resulting_area_id' => $resultingAreaId,
            'review_note' => $reviewNote,
            'reviewed_at' => Carbon::instance($reviewedAt),
        ])->save();

        return $this->map($row->refresh());
    }

    public function markRejected(
        string $suggestionId,
        string $reviewerUserId,
        ?string $reviewNote,
        DateTimeImmutable $reviewedAt,
    ): AreaSuggestion {
        /** @var AreaSuggestionRecord $row */
        $row = AreaSuggestionRecord::query()->findOrFail($suggestionId);
        $row->forceFill([
            'status' => AreaSuggestionStatus::Rejected->value,
            'reviewed_by_user_id' => $reviewerUserId,
            'review_note' => $reviewNote,
            'reviewed_at' => Carbon::instance($reviewedAt),
        ])->save();

        return $this->map($row->refresh());
    }

    public function promoteToArea(string $suggestionId, string $zoneId, string $name): Area
    {
        if ($this->findById($suggestionId) === null) {
            throw new RuntimeException("Unknown suggestion: {$suggestionId}");
        }

        $areaId = (string) Str::uuid();
        $code = Str::slug($name);

        $record = new AreaRecord;
        $record->forceFill([
            'id' => $areaId,
            'zone_id' => $zoneId,
            'code' => $code,
            'name' => $name,
        ])->save();

        return new Area(id: $areaId, zoneId: $zoneId, code: $code, name: $name);
    }

    private function map(AreaSuggestionRecord $row): AreaSuggestion
    {
        $submittedAt = $row->getAttribute('submitted_at');
        $reviewedAt = $row->getAttribute('reviewed_at');

        return new AreaSuggestion(
            id: (string) $row->getAttribute('id'),
            cityHubId: (string) $row->getAttribute('city_hub_id'),
            proposedZoneId: $row->getAttribute('proposed_zone_id') !== null
                ? (string) $row->getAttribute('proposed_zone_id')
                : null,
            proposedName: (string) $row->getAttribute('proposed_name'),
            note: $row->getAttribute('note') !== null ? (string) $row->getAttribute('note') : null,
            submittedByUserId: (string) $row->getAttribute('submitted_by_user_id'),
            status: AreaSuggestionStatus::from((string) $row->getAttribute('status')),
            reviewedByUserId: $row->getAttribute('reviewed_by_user_id') !== null
                ? (string) $row->getAttribute('reviewed_by_user_id')
                : null,
            reviewNote: $row->getAttribute('review_note') !== null
                ? (string) $row->getAttribute('review_note')
                : null,
            resultingAreaId: $row->getAttribute('resulting_area_id') !== null
                ? (string) $row->getAttribute('resulting_area_id')
                : null,
            submittedAt: $submittedAt instanceof Carbon
                ? $submittedAt->toDateTimeImmutable()
                : new DateTimeImmutable((string) $submittedAt),
            reviewedAt: $reviewedAt === null
                ? null
                : ($reviewedAt instanceof Carbon
                    ? $reviewedAt->toDateTimeImmutable()
                    : new DateTimeImmutable((string) $reviewedAt)),
        );
    }
}
