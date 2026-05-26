<?php

declare(strict_types=1);

namespace Kalaanba\Modules\Season\Infrastructure\Eloquent;

use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Kalaanba\Modules\Season\Domain\SeasonPhase;
use Kalaanba\Modules\Season\Domain\SeasonRepository;
use Kalaanba\Modules\Season\Domain\SeasonView;
use Kalaanba\Modules\Season\Domain\SeasonWindow;

/**
 * Postgres-backed adapter for the Domain `SeasonRepository` port.
 */
final class EloquentSeasonRepository implements SeasonRepository
{
    public function findByCode(string $code): ?SeasonView
    {
        $record = SeasonRecord::query()->where('code', $code)->first();

        return $record ? $this->hydrate($record) : null;
    }

    public function findContaining(DateTimeImmutable $at): ?SeasonView
    {
        $record = SeasonRecord::query()
            ->where('starts_at', '<=', $at)
            ->where('archive_window_ends_at', '>=', $at)
            ->orderByDesc('starts_at')
            ->first();

        return $record ? $this->hydrate($record) : null;
    }

    public function upsertFromWindow(SeasonWindow $window, SeasonPhase $initialPhase): SeasonView
    {
        $record = SeasonRecord::query()->where('code', $window->code)->first();

        if ($record === null) {
            $record = new SeasonRecord;
            $record->id = (string) Str::uuid();
            $record->code = $window->code;
            $record->created_at = Carbon::now();
        }

        $record->starts_at = Carbon::instance($window->startsAt);
        $record->ends_at = Carbon::instance($window->endsAt);
        $record->participation_cutoff_at = Carbon::instance($window->participationCutoffAt);
        $record->closing_window_ends_at = Carbon::instance($window->closingWindowEndsAt);
        $record->archive_window_ends_at = Carbon::instance($window->archiveWindowEndsAt);
        $record->phase = $initialPhase->value;
        $record->key_dates = $window->keyDates();
        $record->updated_at = Carbon::now();
        $record->save();

        return $this->hydrate($record);
    }

    public function recordPhaseTransition(string $seasonId, SeasonPhase $newPhase, DateTimeImmutable $occurredAt): SeasonView
    {
        /** @var SeasonRecord $record */
        $record = SeasonRecord::query()->findOrFail($seasonId);
        $record->phase = $newPhase->value;
        $record->updated_at = Carbon::instance($occurredAt);
        if ($newPhase === SeasonPhase::Archived && $record->archived_at === null) {
            $record->archived_at = Carbon::instance($occurredAt);
        }
        $record->save();

        return $this->hydrate($record);
    }

    private function hydrate(SeasonRecord $record): SeasonView
    {
        $tz = new DateTimeZone('UTC');
        $archivedAt = $record->archived_at
            ? new DateTimeImmutable($record->archived_at->format('Y-m-d H:i:s'), $tz)
            : null;

        return new SeasonView(
            id: (string) $record->id,
            code: (string) $record->code,
            startsAt: new DateTimeImmutable($record->starts_at->format('Y-m-d H:i:s'), $tz),
            endsAt: new DateTimeImmutable($record->ends_at->format('Y-m-d H:i:s'), $tz),
            participationCutoffAt: new DateTimeImmutable($record->participation_cutoff_at->format('Y-m-d H:i:s'), $tz),
            phase: SeasonPhase::from((string) $record->phase),
            keyDates: (array) $record->key_dates,
            archivedAt: $archivedAt,
        );
    }
}
