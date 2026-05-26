<?php

declare(strict_types=1);

namespace Kalaanba\Modules\Zone\Application;

use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Support\Facades\DB;
use Kalaanba\Modules\Zone\Domain\AreaSuggestion;
use Kalaanba\Modules\Zone\Domain\AreaSuggestionRepository;
use Kalaanba\Modules\Zone\Domain\AreaSuggestionStatus;
use Kalaanba\Support\EventBus\OutboxEnvelope;
use Kalaanba\Support\EventBus\OutboxWriter;
use Ramsey\Uuid\Uuid;
use RuntimeException;

/**
 * Use case: admin approves a pending Area suggestion.
 *
 * Promotes the suggestion into the `areas` table and emits
 * `zone.area_approved`. Constitution §1.5 (audited), §1.6 (event-first),
 * §1.14 (idempotent — terminal status short-circuits + deterministic UUIDv5
 * dedupe).
 */
final class ApproveAreaSuggestion
{
    /** Stable namespace for deterministic event IDs (UUIDv5). */
    private const EVENT_ID_NS = '8c2f9d0a-2c5b-4e3e-9c1e-6a3b1a0e0002';

    public function __construct(
        private readonly AreaSuggestionRepository $repository,
        private readonly OutboxWriter $outbox,
    ) {}

    public function execute(
        string $suggestionId,
        string $reviewerUserId,
        string $targetZoneId,
        string $finalName,
        ?string $reviewNote,
    ): AreaSuggestion {
        $existing = $this->repository->findById($suggestionId)
            ?? throw new RuntimeException("Unknown suggestion: {$suggestionId}");

        if ($existing->status !== AreaSuggestionStatus::Pending) {
            return $existing;
        }

        $now = new DateTimeImmutable('now', new DateTimeZone('UTC'));

        return DB::transaction(function () use ($suggestionId, $reviewerUserId, $targetZoneId, $finalName, $reviewNote, $now): AreaSuggestion {
            $area = $this->repository->promoteToArea($suggestionId, $targetZoneId, $finalName);
            $updated = $this->repository->markApproved($suggestionId, $reviewerUserId, $area->id, $reviewNote, $now);

            $this->outbox->write(new OutboxEnvelope(
                eventId: (string) Uuid::uuid5(self::EVENT_ID_NS, "approved:{$suggestionId}"),
                eventName: 'zone.area_approved',
                schemaVersion: 1,
                occurredAt: $now,
                actorId: $reviewerUserId,
                actorRole: 'admin',
                source: 'zone',
                payload: [
                    'suggestion_id' => $suggestionId,
                    'area_id' => $area->id,
                    'zone_id' => $area->zoneId,
                    'name' => $area->name,
                    'reviewed_by_user_id' => $reviewerUserId,
                    'reviewed_at' => $now->format(DATE_ATOM),
                ],
            ));

            return $updated;
        });
    }
}
