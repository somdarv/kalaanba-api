<?php

declare(strict_types=1);

namespace Kalaanba\Modules\PlayerAffiliation\Infrastructure\Eloquent;

use DateTimeImmutable;
use Illuminate\Support\Carbon;
use Kalaanba\Modules\PlayerAffiliation\Domain\Affiliation;
use Kalaanba\Modules\PlayerAffiliation\Domain\AffiliationRepository;
use Kalaanba\Modules\PlayerAffiliation\Domain\AffiliationState;

final class EloquentAffiliationRepository implements AffiliationRepository
{
    public function findById(string $id): ?Affiliation
    {
        /** @var AffiliationRecord|null $row */
        $row = AffiliationRecord::query()->find($id);

        return $row === null ? null : $this->map($row);
    }

    public function findByPlayerAndClub(string $playerId, string $clubId): ?Affiliation
    {
        /** @var AffiliationRecord|null $row */
        $row = AffiliationRecord::query()
            ->where('player_id', $playerId)
            ->where('club_id', $clubId)
            ->first();

        return $row === null ? null : $this->map($row);
    }

    public function create(Affiliation $affiliation): Affiliation
    {
        $record = new AffiliationRecord;
        $record->forceFill([
            'id' => $affiliation->id,
            'player_id' => $affiliation->playerId,
            'club_id' => $affiliation->clubId,
            'state' => $affiliation->state->value,
            'requested_by_user_id' => $affiliation->requestedByUserId,
            'requested_at' => Carbon::instance($affiliation->requestedAt),
            'created_at' => Carbon::instance($affiliation->requestedAt),
            'updated_at' => Carbon::instance($affiliation->requestedAt),
        ])->save();

        return $this->map($record->refresh());
    }

    public function transitionState(
        string $affiliationId,
        AffiliationState $state,
        string $decidedByUserId,
        DateTimeImmutable $decidedAt,
    ): Affiliation {
        /** @var AffiliationRecord $row */
        $row = AffiliationRecord::query()->findOrFail($affiliationId);
        $row->forceFill([
            'state' => $state->value,
            'decided_by_user_id' => $decidedByUserId,
            'decided_at' => Carbon::instance($decidedAt),
            'updated_at' => Carbon::instance($decidedAt),
        ])->save();

        return $this->map($row->refresh());
    }

    public function listPendingForClub(string $clubId): array
    {
        return AffiliationRecord::query()
            ->where('club_id', $clubId)
            ->where('state', AffiliationState::Requested->value)
            ->orderByDesc('requested_at')
            ->get()
            ->map(fn (AffiliationRecord $row): Affiliation => $this->map($row))
            ->all();
    }

    private function map(AffiliationRecord $row): Affiliation
    {
        $requestedAt = $row->getAttribute('requested_at');
        $decidedAt = $row->getAttribute('decided_at');

        return new Affiliation(
            id: (string) $row->getAttribute('id'),
            playerId: (string) $row->getAttribute('player_id'),
            clubId: (string) $row->getAttribute('club_id'),
            state: AffiliationState::from((string) $row->getAttribute('state')),
            requestedByUserId: (string) $row->getAttribute('requested_by_user_id'),
            decidedByUserId: $row->getAttribute('decided_by_user_id') !== null
                ? (string) $row->getAttribute('decided_by_user_id')
                : null,
            requestedAt: $requestedAt instanceof Carbon
                ? $requestedAt->toDateTimeImmutable()
                : new DateTimeImmutable((string) $requestedAt),
            decidedAt: $decidedAt === null
                ? null
                : ($decidedAt instanceof Carbon
                    ? $decidedAt->toDateTimeImmutable()
                    : new DateTimeImmutable((string) $decidedAt)),
        );
    }
}
