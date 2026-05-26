<?php

declare(strict_types=1);

namespace Kalaanba\Modules\Zone\Application;

use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Kalaanba\Modules\Zone\Domain\AreaSuggestion;
use Kalaanba\Modules\Zone\Domain\AreaSuggestionRepository;
use Kalaanba\Modules\Zone\Domain\AreaSuggestionStatus;
use Kalaanba\Modules\Zone\Domain\GeographyReader;
use Kalaanba\Support\EventBus\OutboxEnvelope;
use Kalaanba\Support\EventBus\OutboxWriter;
use RuntimeException;

/**
 * Use case: a user submits an Area suggestion for a City Hub.
 *
 * Emits `zone.area_suggested` v1. Engine doc §5; Constitution §1.6
 * (event-first), §1.14 (idempotent — natural key is the suggestion UUID).
 */
final class SubmitAreaSuggestion
{
    public function __construct(
        private readonly AreaSuggestionRepository $repository,
        private readonly GeographyReader $reader,
        private readonly OutboxWriter $outbox,
    ) {}

    public function execute(
        string $cityHubId,
        ?string $proposedZoneId,
        string $proposedName,
        ?string $note,
        string $submittedByUserId,
    ): AreaSuggestion {
        if ($this->reader->findCityHubById($cityHubId) === null) {
            throw new RuntimeException("Unknown city_hub_id: {$cityHubId}");
        }
        if ($proposedZoneId !== null && $this->reader->findZoneById($proposedZoneId) === null) {
            throw new RuntimeException("Unknown zone_id: {$proposedZoneId}");
        }

        $now = new DateTimeImmutable('now', new DateTimeZone('UTC'));
        $draft = new AreaSuggestion(
            id: (string) Str::uuid(),
            cityHubId: $cityHubId,
            proposedZoneId: $proposedZoneId,
            proposedName: $proposedName,
            note: $note,
            submittedByUserId: $submittedByUserId,
            status: AreaSuggestionStatus::Pending,
            reviewedByUserId: null,
            reviewNote: null,
            resultingAreaId: null,
            submittedAt: $now,
            reviewedAt: null,
        );

        return DB::transaction(function () use ($draft, $now): AreaSuggestion {
            $saved = $this->repository->submit($draft);
            $this->outbox->write(new OutboxEnvelope(
                eventId: $saved->id,
                eventName: 'zone.area_suggested',
                schemaVersion: 1,
                occurredAt: $now,
                actorId: $saved->submittedByUserId,
                actorRole: 'user',
                source: 'zone',
                payload: [
                    'suggestion_id' => $saved->id,
                    'city_hub_id' => $saved->cityHubId,
                    'proposed_zone_id' => $saved->proposedZoneId,
                    'proposed_name' => $saved->proposedName,
                    'submitted_by_user_id' => $saved->submittedByUserId,
                    'submitted_at' => $now->format(DATE_ATOM),
                ],
            ));

            return $saved;
        });
    }
}
