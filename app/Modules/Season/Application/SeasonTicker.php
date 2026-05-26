<?php

declare(strict_types=1);

namespace Kalaanba\Modules\Season\Application;

use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Support\Facades\DB;
use Kalaanba\Modules\Season\Domain\SeasonCalendar;
use Kalaanba\Modules\Season\Domain\SeasonConfigProvider;
use Kalaanba\Modules\Season\Domain\SeasonPhase;
use Kalaanba\Modules\Season\Domain\SeasonRepository;
use Kalaanba\Modules\Season\Domain\SeasonView;
use Kalaanba\Modules\Season\Domain\SeasonWindow;
use Kalaanba\Support\EventBus\OutboxEnvelope;
use Kalaanba\Support\EventBus\OutboxWriter;
use Ramsey\Uuid\Uuid;

/**
 * Use case: run one tick of the season scheduler. Computes the phase that
 * SHOULD be in effect right now and, if it differs from the persisted
 * phase, records the transition and emits a `season.phase_changed` event.
 *
 * Idempotent: dedupe keys are deterministic (UUIDv5 over season_id +
 * boundary kind + target_at), so re-runs are safe (Constitution §1.14).
 *
 * Engine doc: docs/engines/season/Season_Engine_UPDATED.md §11.
 */
final class SeasonTicker
{
    /** Stable namespace for deterministic event IDs (UUIDv5). */
    private const EVENT_ID_NS = '8c2f9d0a-2c5b-4e3e-9c1e-6a3b1a0e0001';

    public function __construct(
        private readonly SeasonRepository $repository,
        private readonly SeasonConfigProvider $configLoader,
        private readonly OutboxWriter $outbox,
        private readonly CurrentSeason $currentSeason,
    ) {}

    /** @return array<string, int> emit counts keyed by event_name */
    public function tick(?DateTimeImmutable $now = null): array
    {
        $now ??= new DateTimeImmutable('now', new DateTimeZone('UTC'));

        $calendar = new SeasonCalendar($this->configLoader->load());
        $window = $calendar->windowFor($now);
        $desiredPhase = $calendar->phaseAt($window, $now);

        $current = $this->repository->findByCode($window->code)
            ?? $this->repository->upsertFromWindow($window, $desiredPhase);

        $emitted = ['season.phase_changed' => 0, 'season.cutoff_passed' => 0, 'season.rp_reset_due' => 0];

        if ($current->phase !== $desiredPhase) {
            $phaseEventId = $this->deterministicId("phase:{$current->id}:{$desiredPhase->value}");
            $rpResetEventId = $this->deterministicId("rp_reset:{$current->id}");
            $shouldEmitPhase = ! $this->alreadyEmitted($phaseEventId);
            $shouldEmitRpReset = $desiredPhase === SeasonPhase::Archived
                && ! $this->alreadyEmitted($rpResetEventId);

            DB::transaction(function () use ($current, $desiredPhase, $now, $shouldEmitPhase, $shouldEmitRpReset, $phaseEventId, $rpResetEventId, &$emitted): void {
                $updated = $this->repository->recordPhaseTransition($current->id, $desiredPhase, $now);

                if ($shouldEmitPhase) {
                    $this->emitPhaseChanged($phaseEventId, $current, $updated, $now);
                    $emitted['season.phase_changed']++;
                }

                if ($shouldEmitRpReset) {
                    $this->emitRpResetDue($rpResetEventId, $updated, $now);
                    $emitted['season.rp_reset_due']++;
                }
            });

            $this->currentSeason->forget();
        }

        // Cutoff fan-out: only emit once per cutoff per season (deterministic UUID).
        foreach ($this->dueCutoffs($current, $window, $now) as $cutoffKey => $cutoffAt) {
            $eventId = $this->deterministicId("cutoff:{$current->id}:{$cutoffKey}");
            if ($this->alreadyEmitted($eventId)) {
                continue;
            }
            DB::transaction(function () use ($eventId, $current, $cutoffKey, $cutoffAt, $now, &$emitted): void {
                $this->emitCutoffPassed($eventId, $current, $cutoffKey, $cutoffAt, $now);
                $emitted['season.cutoff_passed']++;
            });
        }

        return $emitted;
    }

    /**
     * @return array<string, DateTimeImmutable>
     */
    private function dueCutoffs(SeasonView $season, SeasonWindow $window, DateTimeImmutable $now): array
    {
        $candidates = [
            'participation_cutoff_at' => $window->participationCutoffAt,
            'new_ranked_challenge_cutoff_at' => $window->newRankedChallengeCutoffAt,
            'ranked_acceptance_cutoff_at' => $window->rankedAcceptanceCutoffAt,
        ];

        return array_filter(
            $candidates,
            fn (DateTimeImmutable $at) => $at <= $now,
        );
    }

    private function alreadyEmitted(string $eventId): bool
    {
        return DB::table('outbox_events')->where('event_id', $eventId)->exists();
    }

    private function emitPhaseChanged(string $eventId, SeasonView $before, SeasonView $after, DateTimeImmutable $occurredAt): void
    {
        $this->outbox->write(new OutboxEnvelope(
            eventId: $eventId,
            eventName: 'season.phase_changed',
            schemaVersion: 1,
            occurredAt: $occurredAt,
            actorId: null,
            actorRole: 'system',
            source: 'season',
            payload: [
                'season_id' => $after->id,
                'season_code' => $after->code,
                'from_phase' => $before->phase->value,
                'to_phase' => $after->phase->value,
                'occurred_at' => $occurredAt->format(DATE_ATOM),
            ],
        ));
    }

    private function emitCutoffPassed(string $eventId, SeasonView $season, string $cutoffKey, DateTimeImmutable $cutoffAt, DateTimeImmutable $occurredAt): void
    {
        $this->outbox->write(new OutboxEnvelope(
            eventId: $eventId,
            eventName: 'season.cutoff_passed',
            schemaVersion: 1,
            occurredAt: $occurredAt,
            actorId: null,
            actorRole: 'system',
            source: 'season',
            payload: [
                'season_id' => $season->id,
                'season_code' => $season->code,
                'cutoff_key' => $cutoffKey,
                'cutoff_at' => $cutoffAt->format(DATE_ATOM),
            ],
        ));
    }

    private function emitRpResetDue(string $eventId, SeasonView $archived, DateTimeImmutable $occurredAt): void
    {
        $parts = sscanf($archived->code, '%d/%d');
        $startYear = is_array($parts) && isset($parts[0]) ? (int) $parts[0] : 0;
        $nextCode = sprintf('%04d/%04d', $startYear + 1, $startYear + 2);

        $this->outbox->write(new OutboxEnvelope(
            eventId: $eventId,
            eventName: 'season.rp_reset_due',
            schemaVersion: 1,
            occurredAt: $occurredAt,
            actorId: null,
            actorRole: 'system',
            source: 'season',
            payload: [
                'season_id' => $archived->id,
                'season_code' => $archived->code,
                'next_season_code' => $nextCode,
                'occurred_at' => $occurredAt->format(DATE_ATOM),
            ],
        ));
    }

    /**
     * Deterministic UUIDv5 — same logical event always produces the same
     * `event_id`. Combined with `outbox_events.event_id UNIQUE` this makes
     * the writer idempotent across re-runs (Constitution §1.14).
     */
    private function deterministicId(string $name): string
    {
        return (string) Uuid::uuid5(self::EVENT_ID_NS, $name);
    }
}
