<?php

declare(strict_types=1);

namespace Kalaanba\Modules\Club\Application;

use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Kalaanba\Modules\Club\Domain\Club;
use Kalaanba\Modules\Club\Domain\ClubMembership;
use Kalaanba\Modules\Club\Domain\ClubRepository;
use Kalaanba\Modules\Club\Domain\ClubRole;
use Kalaanba\Modules\Club\Domain\ClubTier;
use Kalaanba\Modules\Club\Domain\ClubVerificationSource;
use Kalaanba\Modules\Club\Domain\ClubVerificationState;
use Kalaanba\Modules\Zone\Domain\GeographyReader;
use Kalaanba\Support\EventBus\OutboxEnvelope;
use Kalaanba\Support\EventBus\OutboxWriter;
use RuntimeException;

/**
 * Use case: a user creates a club and becomes its Owner (engine doc §5, §7).
 *
 * **The tier decides what this does** (ADR-0017). A `amateur` club goes live and
 * may not take a name from `club.name.reserved_terms`. An `professional` club is
 * created at `verification_state = pending`, hidden from every public read
 * until an admin clears it against the document-based upgrade path (§10), and
 * is the only tier that may claim a reserved name.
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
        private readonly ClubVocabulary $vocabulary,
    ) {}

    /**
     * @throws ClubNameReserved A local club tried to take a real club's name.
     * @throws RuntimeException The City Hub or Area does not exist.
     */
    public function execute(
        string $name,
        string $clubType,
        ClubTier $tier,
        string $cityHubId,
        string $areaId,
        ?string $crestUrl,
        string $createdByUserId,
    ): Club {
        // Checked before the location reads, so a refused name costs one
        // in-memory comparison rather than two database round trips.
        $this->guardName($name, $tier);

        if ($this->geography->findCityHubById($cityHubId) === null) {
            throw new RuntimeException("Unknown city_hub_id: {$cityHubId}");
        }
        if ($this->geography->findAreaById($areaId) === null) {
            throw new RuntimeException("Unknown area_id: {$areaId}");
        }

        $now = new DateTimeImmutable('now', new DateTimeZone('UTC'));
        $verificationState = $tier->initialVerificationState();

        $draft = new Club(
            id: (string) Str::uuid(),
            name: $name,
            clubType: $clubType,
            tier: $tier,
            cityHubId: $cityHubId,
            areaId: $areaId,
            crestUrl: $crestUrl,
            maturity: $tier->initialMaturity(),
            verificationState: $verificationState,
            // Only the official door has sought verification, and §10's
            // document-based row is the path it goes down. A local club has no
            // source because it has claimed nothing.
            verificationSource: $verificationState === ClubVerificationState::Pending
                ? ClubVerificationSource::Documents
                : null,
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
                    'tier' => $club->tier->value,
                    // Consumers that put a club in front of the public must
                    // read this before acting. A pending club is not entitled
                    // to the name it carries.
                    'verification_state' => $club->verificationState->value,
                    'city_hub_id' => $club->cityHubId,
                    'area_id' => $club->areaId,
                    'created_by_user_id' => $createdByUserId,
                    'created_at' => $now->format(DATE_ATOM),
                ],
            ));

            return $club;
        });
    }

    /**
     * A local club may not take a name that belongs to a real one. An official
     * club may, and doing so is exactly what the review then checks.
     */
    private function guardName(string $name, ClubTier $tier): void
    {
        if ($tier->mayClaimReservedName()) {
            return;
        }

        $reservedFor = $this->vocabulary->namePolicy()->reservedMatchFor($name);

        if ($reservedFor !== null) {
            throw new ClubNameReserved($name, $reservedFor);
        }
    }
}
