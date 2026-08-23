<?php

declare(strict_types=1);

namespace Kalaanba\Modules\Club\Infrastructure\Eloquent;

use DateTimeImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Kalaanba\Modules\Club\Domain\Club;
use Kalaanba\Modules\Club\Domain\ClubMaturity;
use Kalaanba\Modules\Club\Domain\ClubMembership;
use Kalaanba\Modules\Club\Domain\ClubReader;
use Kalaanba\Modules\Club\Domain\ClubRepository;
use Kalaanba\Modules\Club\Domain\ClubRole;
use Kalaanba\Modules\Club\Domain\ClubTier;
use Kalaanba\Modules\Club\Domain\ClubVerificationSource;
use Kalaanba\Modules\Club\Domain\ClubVerificationState;

/**
 * Eloquent adapter for both the write ({@see ClubRepository}) and read
 * ({@see ClubReader}) ports of the Club engine.
 *
 * **Every public read goes through {@see self::visibleClubs()}.** A club that
 * claimed the `professional` tier sits at `verification_state = 'pending'` bearing
 * a name it has not yet earned, and showing it before an admin clears it is the
 * impersonation the whole packet exists to prevent (ADR-0017). Putting the
 * filter in one query builder rather than at each call site is what stops the
 * next reader added here from forgetting it.
 *
 * {@see self::listAdminClubsForUser()} is the single deliberate exception, and
 * it says so where it breaks the rule.
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
            'tier' => $club->tier->value,
            'crest_url' => $club->crestUrl,
            'maturity_level' => $club->maturity->value,
            'verification_state' => $club->verificationState->value,
            'verification_source' => $club->verificationSource?->value,
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

    public function updateCrestUrl(string $clubId, string $crestUrl, DateTimeImmutable $at): void
    {
        ClubRecord::query()
            ->whereKey($clubId)
            ->update([
                'crest_url' => $crestUrl,
                'updated_at' => Carbon::instance($at),
            ]);
    }

    public function findById(string $id): ?Club
    {
        /** @var ClubRecord|null $row */
        $row = $this->visibleClubs()->find($id);

        return $row === null ? null : $this->mapClub($row);
    }

    public function findByIdIncludingUnverified(string $id): ?Club
    {
        /** @var ClubRecord|null $row */
        $row = ClubRecord::query()->whereNull('archived_at')->find($id);

        return $row === null ? null : $this->mapClub($row);
    }

    public function listByArea(string $areaId, int $limit): array
    {
        return $this->visibleClubs()
            ->where('area_id', $areaId)
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get()
            ->map(fn (ClubRecord $row): Club => $this->mapClub($row))
            ->all();
    }

    /**
     * **The one read that deliberately ignores the pending filter.** An Owner
     * whose official claim is under review must be able to see it; hiding a
     * club from the person who created it reads as the club having vanished,
     * and they file a second claim. Every other read here is filtered.
     */
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

    /**
     * The base query for anything a non-admin may see: not archived (§1.13) and
     * not an unproven claim on someone else's name (ADR-0017).
     *
     * @return Builder<ClubRecord>
     */
    private function visibleClubs(): Builder
    {
        return ClubRecord::query()
            ->whereNull('archived_at')
            ->where('verification_state', '!=', ClubVerificationState::Pending->value);
    }

    private function mapClub(ClubRecord $row): Club
    {
        $createdAt = $row->getAttribute('created_at');

        return new Club(
            id: (string) $row->getAttribute('id'),
            name: (string) $row->getAttribute('name'),
            clubType: (string) $row->getAttribute('club_type'),
            tier: ClubTier::tryFrom((string) $row->getAttribute('tier')) ?? ClubTier::Amateur,
            cityHubId: (string) $row->getAttribute('city_hub_id'),
            areaId: (string) $row->getAttribute('area_id'),
            crestUrl: $row->getAttribute('crest_url') !== null
                ? (string) $row->getAttribute('crest_url')
                : null,
            maturity: ClubMaturity::from((string) $row->getAttribute('maturity_level')),
            verificationState: ClubVerificationState::tryFrom(
                (string) $row->getAttribute('verification_state')
            ) ?? ClubVerificationState::NotRequired,
            verificationSource: $row->getAttribute('verification_source') !== null
                ? ClubVerificationSource::tryFrom((string) $row->getAttribute('verification_source'))
                : null,
            createdByUserId: (string) $row->getAttribute('created_by_user_id'),
            createdAt: $createdAt instanceof Carbon
                ? $createdAt->toDateTimeImmutable()
                : new DateTimeImmutable((string) $createdAt),
        );
    }
}
