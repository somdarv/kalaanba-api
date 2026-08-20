<?php

declare(strict_types=1);

namespace Kalaanba\Modules\Club\Application;

use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Kalaanba\Modules\Club\Domain\Club;
use Kalaanba\Modules\Club\Domain\ClubMaturity;
use Kalaanba\Modules\Club\Domain\ClubMembership;
use Kalaanba\Modules\Club\Domain\ClubRepository;
use Kalaanba\Modules\Club\Domain\ClubRole;
use Kalaanba\Modules\Zone\Domain\GeographyReader;
use Kalaanba\Support\EventBus\OutboxEnvelope;
use Kalaanba\Support\EventBus\OutboxWriter;
use RuntimeException;

/**
 * Use case: a user creates a club and becomes its Owner (engine doc §5, §7).
 *
 * Location is validated against the Zone engine through its read port — the
 * sanctioned cross-engine read (no cross-schema join, §1.1). The club write and
 * the owner-membership write happen in one transaction with the outbox event
 * (§1.6 event-first).
 */
final class CreateClub
{
    public function __construct(
        private readonly ClubRepository $repository,
        private readonly GeographyReader $geography,
        private readonly OutboxWriter $outbox,
    ) {}

    public function execute(
        string $name,
        string $clubType,
        string $cityHubId,
        string $areaId,
        ?string $crestUrl,
        string $createdByUserId,
    ): Club {
        if ($this->geography->findCityHubById($cityHubId) === null) {
            throw new RuntimeException("Unknown city_hub_id: {$cityHubId}");
        }
        if ($this->geography->findAreaById($areaId) === null) {
            throw new RuntimeException("Unknown area_id: {$areaId}");
        }

        $now = new DateTimeImmutable('now', new DateTimeZone('UTC'));
        $draft = new Club(
            id: (string) Str::uuid(),
            name: $name,
            clubType: $clubType,
            cityHubId: $cityHubId,
            areaId: $areaId,
            crestUrl: $crestUrl,
            maturity: ClubMaturity::Informal,
            createdByUserId: $createdByUserId,
            createdAt: $now,
        );

        return DB::transaction(function () use ($draft, $createdByUserId, $now): Club {
            $club = $this->repository->create($draft);

            $this->repository->addMembership(new ClubMembership(
                id: (string) Str::uuid(),
                clubId: $club->id,
                userId: $createdByUserId,
                role: ClubRole::Owner,
                state: 'active',
                createdAt: $now,
            ));

            $this->outbox->write(new OutboxEnvelope(
                eventId: $club->id,
                eventName: 'club.created',
                schemaVersion: 1,
                occurredAt: $now,
                actorId: $createdByUserId,
                actorRole: 'user',
                source: 'club',
                payload: [
                    'club_id' => $club->id,
                    'name' => $club->name,
                    'club_type' => $club->clubType,
                    'city_hub_id' => $club->cityHubId,
                    'area_id' => $club->areaId,
                    'created_by_user_id' => $createdByUserId,
                    'created_at' => $now->format(DATE_ATOM),
                ],
            ));

            return $club;
        });
    }
}
