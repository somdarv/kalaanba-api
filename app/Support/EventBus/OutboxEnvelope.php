<?php

declare(strict_types=1);

namespace Kalaanba\Support\EventBus;

use DateTimeImmutable;
use InvalidArgumentException;

/**
 * Canonical outbox envelope — the single shape every domain event must take
 * before it is written to the outbox and relayed to Redis Streams.
 *
 * Ref: engineering-standards §8 (Events & Outbox)
 * Schema: event_id, event_name, schema_version, occurred_at,
 *         actor_id, actor_role, source, payload
 */
readonly class OutboxEnvelope
{
    /**
     * Pattern enforced on every event_name: exactly one dot separating two
     * lowercase snake_case segments, e.g. "match.result_confirmed".
     */
    private const EVENT_NAME_PATTERN = '/^[a-z][a-z0-9_]*\.[a-z][a-z0-9_]*$/';

    /**
     * @param  array<string, mixed>  $payload  Engine-specific payload — shape defined in contracts/events/.
     */
    public function __construct(
        /** UUIDv4/v7 — unique identity for this specific event occurrence. */
        public readonly string $eventId,

        /** Format: engine.action_past_tense (e.g. "match.result_confirmed"). */
        public readonly string $eventName,

        /** Increment when payload shape changes in a breaking way. */
        public readonly int $schemaVersion,

        /** When the domain event occurred (UTC). */
        public readonly DateTimeImmutable $occurredAt,

        /**
         * ID of the actor who triggered the action, or null for system-
         * initiated events (e.g. scheduled jobs).
         */
        public readonly ?string $actorId,

        /** Role of the actor (e.g. "player", "admin", "system"). */
        public readonly ?string $actorRole,

        /** Originating engine or service (e.g. "match", "rp_economy"). */
        public readonly string $source,

        /** Engine-specific payload — shape is defined in contracts/events/. */
        public readonly array $payload,
    ) {
        if (! preg_match(self::EVENT_NAME_PATTERN, $eventName)) {
            throw new InvalidArgumentException(
                "Event name \"{$eventName}\" must follow the engine.action format "
                .'(lowercase snake_case segments separated by a single dot).'
            );
        }

        if ($schemaVersion < 1) {
            throw new InvalidArgumentException(
                "schema_version must be >= 1, got {$schemaVersion}."
            );
        }
    }

    /**
     * Serialise the envelope to the flat array that outbox_events.payload
     * stores as JSONB.  The full canonical envelope is persisted so that
     * the relay (and consumers) never need to reconstruct fields from
     * multiple columns.
     *
     * @return array<string, mixed>
     */
    public function toPayloadArray(): array
    {
        return [
            'event_id' => $this->eventId,
            'event_name' => $this->eventName,
            'schema_version' => $this->schemaVersion,
            'occurred_at' => $this->occurredAt->format(DateTimeImmutable::ATOM),
            'actor_id' => $this->actorId,
            'actor_role' => $this->actorRole,
            'source' => $this->source,
            'payload' => $this->payload,
        ];
    }
}
