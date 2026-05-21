<?php

declare(strict_types=1);

namespace Kalaanba\Modules\Analytics\Infrastructure;

use Illuminate\Database\ConnectionInterface;
use Kalaanba\Modules\Analytics\Domain\AnalyticsEvent;
use Kalaanba\Modules\Analytics\Domain\Contracts\AnalyticsEventWriter;

/**
 * Persists a validated AnalyticsEvent into analytics.events.
 *
 * Postgres partitioning routes the row into the day-bucket matching
 * occurred_at; AnalyticsEnsurePartitionsCommand guarantees the bucket exists.
 */
final class DatabaseAnalyticsEventWriter implements AnalyticsEventWriter
{
    public function __construct(private readonly ConnectionInterface $connection) {}

    public function write(AnalyticsEvent $event): void
    {
        $this->connection->table('analytics.events')->insert([
            'event_id' => $event->eventId,
            'event_name' => $event->eventName,
            'schema_version' => $event->schemaVersion,
            'occurred_at' => $event->occurredAt->format('Y-m-d H:i:s.uP'),
            'actor_user_id' => $event->actorUserId,
            'actor_role' => $event->actorRole,
            'source' => $event->source,
            'session_id' => $event->sessionId,
            'device_id' => $event->deviceId,
            'route' => $event->route,
            'context' => json_encode($event->context, JSON_THROW_ON_ERROR),
            'properties' => json_encode($event->properties, JSON_THROW_ON_ERROR),
        ]);
    }
}
