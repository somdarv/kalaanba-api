<?php

declare(strict_types=1);

namespace Kalaanba\Modules\PlayerAffiliation\Application;

use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Support\Facades\DB;
use Kalaanba\Modules\Club\Domain\ClubReader;
use Kalaanba\Modules\PlayerAffiliation\Domain\Affiliation;
use Kalaanba\Modules\PlayerAffiliation\Domain\AffiliationRepository;
use Kalaanba\Modules\PlayerAffiliation\Domain\AffiliationState;
use Kalaanba\Support\EventBus\OutboxEnvelope;
use Kalaanba\Support\EventBus\OutboxWriter;
use Ramsey\Uuid\Uuid;
use RuntimeException;

/**
 * Use case: a club Owner/Admin accepts or declines a pending join request
 * (engine doc §8, §11). Only an admin of the affiliation's club may decide
 * (authorised via the Club read port). A decided request transitions
 * `requested` → `active` (accept) or `declined`, emitting the matching event.
 */
final class DecideJoinRequest
{
    /** Stable namespace for deterministic decision event IDs (UUIDv5). */
    private const EVENT_ID_NS = '8c2f9d0a-2c5b-4e3e-9c1e-6a3b1a0e0003';

    public function __construct(
        private readonly AffiliationRepository $affiliations,
        private readonly ClubReader $clubs,
        private readonly OutboxWriter $outbox,
    ) {}

    public function execute(string $affiliationId, string $actingUserId, bool $accept): Affiliation
    {
        $affiliation = $this->affiliations->findById($affiliationId);
        if ($affiliation === null) {
            throw new RuntimeException("Unknown join request: {$affiliationId}");
        }
        if (! $this->clubs->userIsClubAdmin($affiliation->clubId, $actingUserId)) {
            throw new AffiliationDenied('Only a club owner or admin can decide join requests.');
        }
        if ($affiliation->state !== AffiliationState::Requested) {
            throw new RuntimeException('This join request has already been decided.');
        }

        $now = new DateTimeImmutable('now', new DateTimeZone('UTC'));
        $next = $accept ? AffiliationState::Active : AffiliationState::Declined;
        $eventName = $accept ? 'affiliation.activated' : 'affiliation.declined';

        return DB::transaction(function () use ($affiliationId, $next, $actingUserId, $now, $affiliation, $eventName): Affiliation {
            $updated = $this->affiliations->transitionState($affiliationId, $next, $actingUserId, $now);
            $this->outbox->write(new OutboxEnvelope(
                eventId: (string) Uuid::uuid5(self::EVENT_ID_NS, $eventName.':'.$affiliation->id),
                eventName: $eventName,
                schemaVersion: 1,
                occurredAt: $now,
                actorId: $actingUserId,
                actorRole: 'user',
                source: 'player-affiliation',
                payload: [
                    'affiliation_id' => $updated->id,
                    'player_id' => $updated->playerId,
                    'club_id' => $updated->clubId,
                    'state' => $updated->state->value,
                    'decided_by_user_id' => $actingUserId,
                    'decided_at' => $now->format(DATE_ATOM),
                ],
            ));

            return $updated;
        });
    }
}
