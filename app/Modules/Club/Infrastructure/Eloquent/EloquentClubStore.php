<?php

declare(strict_types=1);

namespace Kalaanba\Modules\Club\Infrastructure\Eloquent;

use DateTimeImmutable;
use Illuminate\Support\Carbon;
use Kalaanba\Modules\Club\Domain\Club;
use Kalaanba\Modules\Club\Domain\ClubMaturity;
use Kalaanba\Modules\Club\Domain\ClubMembership;
use Kalaanba\Modules\Club\Domain\ClubReader;
use Kalaanba\Modules\Club\Domain\ClubRepository;
use Kalaanba\Modules\Club\Domain\ClubRole;

/**
 * Eloquent adapter for both the write ({@see ClubRepository}) and read
 * ({@see ClubReader}) ports of the Club engine.
 */
final class EloquentClubStore implements ClubReader, ClubRepository
{
    public function create(Club $club): Club
    {
        $record = new ClubRecord;
        $record->forceFill([
            'id' => $club->id,
            'name' => $club->name,
            'club_type' => $club->clubType,
            'city_hub_id' => $club->cityHubId,
            'area_id' => $club->areaId,
            'crest_url' => $club->crestUrl,
            'maturity_level' => $club->maturity->value,
            'created_by_user_id' => $club->createdByUserId,
            'created_at' => Carbon::instance($club->createdAt),
            'updated_at' => Carbon::instance($club->createdAt),
        ])->save();

        return $this->mapClub($record->refresh());
    }

    public function addMembership(ClubMembership $membership): ClubMembership
    {
        $record = new ClubMembershipRecord;
        $record->forceFill([
            'id' => $membership->id,
            'club_id' => $membership->clubId,
            'user_id' => $membership->userId,
            'role' => $membership->role->value,
            'state' => $membership->state,
            'created_at' => Carbon::instance($membership->createdAt),
        ])->save();

        return $membership;
    }

    public function findById(string $id): ?Club
    {
        /** @var ClubRecord|null $row */
        $row = ClubRecord::query()->whereNull('archived_at')->find($id);

        return $row === null ? null : $this->mapClub($row);
    }

    public function listByArea(string $areaId, int $limit): array
    {
        return ClubRecord::query()
            ->where('area_id', $areaId)
            ->whereNull('archived_at')
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get()
            ->map(fn (ClubRecord $row): Club => $this->mapClub($row))
            ->all();
    }

    public function listAdminClubsForUser(string $userId): array
    {
        $adminRoles = [
            ClubRole::Owner->value,
            ClubRole::Cofounder->value,
            ClubRole::Admin->value,
        ];

        return ClubRecord::query()
            ->join('club_memberships', 'club_memberships.club_id', '=', 'clubs.id')
            ->where('club_memberships.user_id', $userId)
            ->where('club_memberships.state', 'active')
            ->whereIn('club_memberships.role', $adminRoles)
            ->whereNull('club_memberships.archived_at')
            ->whereNull('clubs.archived_at')
            ->orderByDesc('clubs.created_at')
            ->select('clubs.*')
            ->get()
            ->map(fn (ClubRecord $row): Club => $this->mapClub($row))
            ->all();
    }

    public function userIsClubAdmin(string $clubId, string $userId): bool
    {
        return ClubMembershipRecord::query()
            ->where('club_id', $clubId)
            ->where('user_id', $userId)
            ->where('state', 'active')
            ->whereIn('role', [
                ClubRole::Owner->value,
                ClubRole::Cofounder->value,
                ClubRole::Admin->value,
            ])
            ->whereNull('archived_at')
            ->exists();
    }

    private function mapClub(ClubRecord $row): Club
    {
        $createdAt = $row->getAttribute('created_at');

        return new Club(
            id: (string) $row->getAttribute('id'),
            name: (string) $row->getAttribute('name'),
            clubType: (string) $row->getAttribute('club_type'),
            cityHubId: (string) $row->getAttribute('city_hub_id'),
            areaId: (string) $row->getAttribute('area_id'),
            crestUrl: $row->getAttribute('crest_url') !== null
                ? (string) $row->getAttribute('crest_url')
                : null,
            maturity: ClubMaturity::from((string) $row->getAttribute('maturity_level')),
            createdByUserId: (string) $row->getAttribute('created_by_user_id'),
            createdAt: $createdAt instanceof Carbon
                ? $createdAt->toDateTimeImmutable()
                : new DateTimeImmutable((string) $createdAt),
        );
    }
}
