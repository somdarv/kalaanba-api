<?php

declare(strict_types=1);

namespace Kalaanba\Modules\PlayerAffiliation\Application;

use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Kalaanba\Modules\PlayerAffiliation\Domain\Player;
use Kalaanba\Modules\PlayerAffiliation\Domain\PlayerAvailability;
use Kalaanba\Modules\PlayerAffiliation\Domain\PlayerClaimStatus;
use Kalaanba\Modules\PlayerAffiliation\Domain\PlayerMarketStatus;
use Kalaanba\Modules\PlayerAffiliation\Domain\PlayerRepository;
use Kalaanba\Support\EventBus\OutboxEnvelope;
use Kalaanba\Support\EventBus\OutboxWriter;

/**
 * Use case: a user creates their player profile and becomes a CLAIMED
 * FREE-AGENT player (engine doc §4, §22).
 *
 * One player per user in V1 — re-submission returns the existing player
 * without re-emitting (Constitution §1.14 idempotent). Emits
 * `player.profile_created` v1 through the outbox in the same transaction as
 * the write (§1.6 event-first).
 */
final class CreatePlayerProfile
{
    public function __construct(
        private readonly PlayerRepository $repository,
        private readonly OutboxWriter $outbox,
    ) {}

    /**
     * @return array{player: Player, created: bool}
     */
    public function execute(
        string $userId,
        string $firstName,
        string $lastName,
        string $stageName,
        ?int $preferredNumber,
        ?string $primaryPosition,
        PlayerAvailability $availability,
        ?string $headshotUrl,
    ): array {
        $existing = $this->repository->findByUserId($userId);
        if ($existing !== null) {
            return ['player' => $existing, 'created' => false];
        }

        $now = new DateTimeImmutable('now', new DateTimeZone('UTC'));
        $draft = new Player(
            id: (string) Str::uuid(),
            userId: $userId,
            firstName: $firstName,
            lastName: $lastName,
            stageName: $stageName,
            preferredNumber: $preferredNumber,
            primaryPosition: $primaryPosition,
            availability: $availability,
            marketStatus: PlayerMarketStatus::FreeAgent,
            claimStatus: PlayerClaimStatus::Claimed,
            headshotUrl: $headshotUrl,
            createdAt: $now,
        );

        return DB::transaction(function () use ($draft, $now): array {
            $saved = $this->repository->create($draft);
            $this->outbox->write(new OutboxEnvelope(
                eventId: $saved->id,
                eventName: 'player.profile_created',
                schemaVersion: 1,
                occurredAt: $now,
                actorId: $saved->userId,
                actorRole: 'user',
                source: 'player-affiliation',
                payload: [
                    'player_id' => $saved->id,
                    'user_id' => $saved->userId,
                    'stage_name' => $saved->stageName,
                    'primary_position' => $saved->primaryPosition,
                    'availability_status' => $saved->availability->value,
                    'market_status' => $saved->marketStatus->value,
                    'claim_status' => $saved->claimStatus->value,
                    'created_at' => $now->format(DATE_ATOM),
                ],
            ));

            return ['player' => $saved, 'created' => true];
        });
    }
}
