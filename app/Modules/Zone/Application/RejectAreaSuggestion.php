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
 * Use case: admin rejects a pending Area suggestion.
 *
 * Records rejection (status + reviewer + note + timestamp) and emits
 * `zone.area_rejected`. Suggestion row is preserved for audit
 * (Constitution §1.13). Idempotent — terminal status short-circuits.
 */
final class RejectAreaSuggestion
{
    private const EVENT_ID_NS = '8c2f9d0a-2c5b-4e3e-9c1e-6a3b1a0e0003';

    public function __construct(
        private readonly AreaSuggestionRepository $repository,
        private readonly OutboxWriter $outbox,
    ) {}

    public function execute(
        string $suggestionId,
        string $reviewerUserId,
        ?string $reviewNote,
    ): AreaSuggestion {
        $existing = $this->repository->findById($suggestionId)
            ?? throw new RuntimeException("Unknown suggestion: {$suggestionId}");

        if ($existing->status !== AreaSuggestionStatus::Pending) {
            return $existing;
        }

        $now = new DateTimeImmutable('now', new DateTimeZone('UTC'));

        return DB::transaction(function () use ($suggestionId, $reviewerUserId, $reviewNote, $now): AreaSuggestion {
            $updated = $this->repository->markRejected($suggestionId, $reviewerUserId, $reviewNote, $now);

            $this->outbox->write(new OutboxEnvelope(
                eventId: (string) Uuid::uuid5(self::EVENT_ID_NS, "rejected:{$suggestionId}"),
                eventName: 'zone.area_rejected',
                schemaVersion: 1,
                occurredAt: $now,
                actorId: $reviewerUserId,
                actorRole: 'admin',
                source: 'zone',
                payload: [
                    'suggestion_id' => $suggestionId,
                    'reviewed_by_user_id' => $reviewerUserId,
                    'reviewed_at' => $now->format(DATE_ATOM),
                ],
            ));

            return $updated;
        });
    }
}
