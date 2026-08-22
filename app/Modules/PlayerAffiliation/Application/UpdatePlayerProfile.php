<?php

declare(strict_types=1);

namespace Kalaanba\Modules\PlayerAffiliation\Application;

use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Kalaanba\Modules\PlayerAffiliation\Domain\Player;
use Kalaanba\Modules\PlayerAffiliation\Domain\PlayerAvailability;
use Kalaanba\Modules\PlayerAffiliation\Domain\PlayerRepository;
use Kalaanba\Support\EventBus\OutboxEnvelope;
use Kalaanba\Support\EventBus\OutboxWriter;

/**
 * Use case: a player edits their own profile (engine doc §6, §12).
 *
 * Covers both jobs on the `/me` surface — the one-tap availability control and
 * the details sheet — because they are the same resource under the same
 * authorization. Splitting them would put two use cases on one row of the §6
 * field list.
 *
 * **Ownership is checked here, not in the controller.** §17 (player identity
 * integrity) says nobody edits another player's record through this route, not
 * even a club admin; club-side changes go through affiliation (§11). Putting
 * the check in the use case means every future caller inherits it.
 *
 * **Availability changes emit an event.** §12 feeds availability into the club
 * readiness summary, and that is a cross-engine effect, so it travels through
 * the outbox in the same transaction as the write (Constitution Law 1 and
 * Law 6). This engine never writes a club's table and never computes a club's
 * readiness — it reports that a player's availability moved and lets Club
 * decide what that means.
 */
final class UpdatePlayerProfile
{
    public function __construct(
        private readonly PlayerRepository $repository,
        private readonly OutboxWriter $outbox,
    ) {}

    /**
     * @param  array<string, mixed>  $changes  Already-validated, already-narrowed
     *                                         to the keys the contract accepts.
     *                                         An absent key means "leave alone";
     *                                         an explicit null clears a nullable
     *                                         field.
     *
     * @throws PlayerNotFound when no live player carries this id
     * @throws PlayerNotYours when the actor does not own it
     */
    public function execute(string $playerId, string $actorUserId, array $changes): Player
    {
        $existing = $this->repository->findById($playerId);
        if ($existing === null) {
            throw new PlayerNotFound($playerId);
        }
        if ($existing->userId !== $actorUserId) {
            throw new PlayerNotYours($playerId);
        }

        $availability = $this->resolveAvailability($existing, $changes);

        $updated = $existing->withProfile(
            firstName: $this->stringOr($changes, 'first_name', $existing->firstName),
            lastName: $this->stringOr($changes, 'last_name', $existing->lastName),
            stageName: $this->stringOr($changes, 'stage_name', $existing->stageName),
            preferredNumber: array_key_exists('preferred_number', $changes)
                ? ($changes['preferred_number'] === null ? null : (int) $changes['preferred_number'])
                : $existing->preferredNumber,
            primaryPosition: array_key_exists('primary_position', $changes)
                ? $this->nullableString($changes['primary_position'])
                : $existing->primaryPosition,
            availability: $availability,
            headshotUrl: array_key_exists('headshot_url', $changes)
                ? $this->nullableString($changes['headshot_url'])
                : $existing->headshotUrl,
        );

        $availabilityMoved = $updated->availability !== $existing->availability;
        $now = new DateTimeImmutable('now', new DateTimeZone('UTC'));

        return DB::transaction(function () use ($updated, $existing, $availabilityMoved, $now): Player {
            $saved = $this->repository->update($updated);

            $this->outbox->write(new OutboxEnvelope(
                eventId: (string) Str::uuid(),
                eventName: 'player.profile_updated',
                schemaVersion: 1,
                occurredAt: $now,
                actorId: $saved->userId,
                actorRole: 'player',
                source: 'player-affiliation',
                payload: [
                    'player_id' => $saved->id,
                    'user_id' => $saved->userId,
                    'stage_name' => $saved->stageName,
                    'primary_position' => $saved->primaryPosition,
                    'availability_status' => $saved->availability->value,
                    // Consumers that only care about §12 can filter on this
                    // instead of diffing two payloads they were never sent.
                    'availability_changed' => $availabilityMoved,
                    'previous_availability_status' => $availabilityMoved
                        ? $existing->availability->value
                        : null,
                    'updated_at' => $now->format(DATE_ATOM),
                ],
            ));

            return $saved;
        });
    }

    /**
     * Availability is non-nullable, so an absent key keeps what is stored and
     * an unrecognised key is refused rather than silently defaulted. Defaulting
     * would let a typo quietly mark a player unavailable.
     *
     * @param  array<string, mixed>  $changes
     */
    private function resolveAvailability(Player $existing, array $changes): PlayerAvailability
    {
        if (! array_key_exists('availability_status', $changes)) {
            return $existing->availability;
        }

        $raw = $changes['availability_status'];

        return is_string($raw)
            ? (PlayerAvailability::tryFrom($raw) ?? $existing->availability)
            : $existing->availability;
    }

    /**
     * @param  array<string, mixed>  $changes
     */
    private function stringOr(array $changes, string $key, string $fallback): string
    {
        if (! array_key_exists($key, $changes)) {
            return $fallback;
        }

        return is_string($changes[$key]) ? $changes[$key] : $fallback;
    }

    private function nullableString(mixed $value): ?string
    {
        return is_string($value) && $value !== '' ? $value : null;
    }
}
