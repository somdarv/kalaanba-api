<?php

declare(strict_types=1);

namespace Kalaanba\Modules\PlayerAffiliation\Application;

use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Kalaanba\Modules\Club\Domain\ClubReader;
use Kalaanba\Modules\PlayerAffiliation\Domain\Affiliation;
use Kalaanba\Modules\PlayerAffiliation\Domain\AffiliationRepository;
use Kalaanba\Modules\PlayerAffiliation\Domain\AffiliationState;
use Kalaanba\Modules\PlayerAffiliation\Domain\PlayerRepository;
use Kalaanba\Support\EventBus\OutboxEnvelope;
use Kalaanba\Support\EventBus\OutboxWriter;
use RuntimeException;

/**
 * Use case: a player requests to join a club (engine doc §8, §11).
 *
 * Resolves the caller's player, verifies the club exists (Club read port —
 * sanctioned cross-engine read), and creates a `requested` affiliation.
 * Idempotent: a repeat while already requested/active returns the existing row
 * without re-emitting. Emits `affiliation.requested` v1 via the outbox.
 */
final class RequestToJoinClub
{
    public function __construct(
        private readonly AffiliationRepository $affiliations,
        private readonly PlayerRepository $players,
        private readonly ClubReader $clubs,
        private readonly OutboxWriter $outbox,
    ) {}

    /**
     * @return array{affiliation: Affiliation, created: bool}
     */
    public function execute(string $userId, string $clubId): array
    {
        $player = $this->players->findByUserId($userId);
        if ($player === null) {
            throw new RuntimeException('You need a player profile before joining a club.');
        }
        if ($this->clubs->findById($clubId) === null) {
            throw new RuntimeException("Unknown club: {$clubId}");
        }

        $existing = $this->affiliations->findByPlayerAndClub($player->id, $clubId);
        if ($existing !== null && in_array($existing->state, [
            AffiliationState::Requested,
            AffiliationState::Active,
        ], true)) {
            return ['affiliation' => $existing, 'created' => false];
        }

        $now = new DateTimeImmutable('now', new DateTimeZone('UTC'));
        $draft = new Affiliation(
            id: (string) Str::uuid(),
            playerId: $player->id,
            clubId: $clubId,
            state: AffiliationState::Requested,
            requestedByUserId: $userId,
            decidedByUserId: null,
            requestedAt: $now,
            decidedAt: null,
        );

        return DB::transaction(function () use ($draft, $now): array {
            $saved = $this->affiliations->create($draft);
            $this->outbox->write(new OutboxEnvelope(
                eventId: $saved->id,
                eventName: 'affiliation.requested',
                schemaVersion: 1,
                occurredAt: $now,
                actorId: $saved->requestedByUserId,
                actorRole: 'user',
                source: 'player-affiliation',
                payload: [
                    'affiliation_id' => $saved->id,
                    'player_id' => $saved->playerId,
                    'club_id' => $saved->clubId,
                    'requested_by_user_id' => $saved->requestedByUserId,
                    'requested_at' => $now->format(DATE_ATOM),
                ],
            ));

            return ['affiliation' => $saved, 'created' => true];
        });
    }
}
