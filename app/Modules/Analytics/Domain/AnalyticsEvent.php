<?php

declare(strict_types=1);

namespace Kalaanba\Modules\Analytics\Domain;

use DateTimeImmutable;
use InvalidArgumentException;

/**
 * Canonical analytics event value object.
 *
 * Shape (engineering-standards §8 + analytics engine doc §9, §10):
 *   event_id, event_name, schema_version, occurred_at,
 *   actor_user_id, actor_role, source, session_id, device_id, route,
 *   context, properties.
 *
 * `context` carries environment metadata (city_hub, zone, etc.).
 * `properties` carries the event-specific bag validated by EventSchema.
 */
final readonly class AnalyticsEvent
{
    /**
     * @param  array<string, mixed>  $context
     * @param  array<string, mixed>  $properties
     */
    public function __construct(
        public string $eventId,
        public string $eventName,
        public int $schemaVersion,
        public DateTimeImmutable $occurredAt,
        public string $source,
        public ?string $actorUserId = null,
        public ?string $actorRole = null,
        public ?string $sessionId = null,
        public ?string $deviceId = null,
        public ?string $route = null,
        public array $context = [],
        public array $properties = [],
    ) {
        if ($eventId === '') {
            throw new InvalidArgumentException('event_id must not be empty.');
        }

        if ($source === '') {
            throw new InvalidArgumentException('source must not be empty.');
        }
    }
}
