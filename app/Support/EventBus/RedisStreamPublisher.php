<?php

declare(strict_types=1);

namespace Kalaanba\Support\EventBus;

use Illuminate\Redis\Connections\Connection;
use Illuminate\Support\Facades\Redis;
use RuntimeException;

/**
 * Publishes an OutboxEnvelope to a Redis Stream.
 *
 * Stream key convention: kalaanba.events.<engine>
 * where <engine> is the segment before the dot in event_name.
 *
 * Example: event_name "match.result_confirmed" → stream "kalaanba.events.match"
 *
 * Each stream entry carries all canonical envelope fields as flat string
 * values.  Consumers XREAD from the stream and re-hydrate the envelope.
 *
 * Ref: engineering-standards §8 (Events & Outbox)
 */
class RedisStreamPublisher
{
    /**
     * Prefix applied to every stream key.
     * Configurable via config('eventbus.stream_prefix').
     */
    private string $streamPrefix;

    public function __construct()
    {
        $this->streamPrefix = (string) config('eventbus.stream_prefix', 'kalaanba.events');
    }

    /**
     * @throws RuntimeException when the XADD command returns a falsy result.
     */
    public function publish(OutboxEnvelope $envelope): void
    {
        $engine = explode('.', $envelope->eventName, 2)[0];
        $streamKey = "{$this->streamPrefix}.{$engine}";

        /** @var Connection $connection */
        $connection = Redis::connection('default');

        // XADD — predis v3 argument order: ($key, $dictionary, $id, $options).
        // Larastan stubs model phpredis's reversed order ($key, $id, $dictionary),
        // so we suppress the type mismatch here. Both work at runtime; the
        // REDIS_CLIENT env var selects which client is active.
        // @phpstan-ignore argument.type, argument.type
        $messageId = $connection->xadd($streamKey, $this->buildFields($envelope), '*');

        if ($messageId === false || $messageId === null) {
            throw new RuntimeException(
                "Redis XADD to stream \"{$streamKey}\" returned no message ID "
                ."for event \"{$envelope->eventId}\"."
            );
        }
    }

    /**
     * Flatten the envelope into the string key/value map Redis Streams require.
     *
     * @return array<string, string>
     */
    private function buildFields(OutboxEnvelope $envelope): array
    {
        return [
            'event_id' => $envelope->eventId,
            'event_name' => $envelope->eventName,
            'schema_version' => (string) $envelope->schemaVersion,
            'occurred_at' => $envelope->occurredAt->format(\DateTimeImmutable::ATOM),
            'actor_id' => $envelope->actorId ?? '',
            'actor_role' => $envelope->actorRole ?? '',
            'source' => $envelope->source,
            'payload' => json_encode($envelope->payload, JSON_THROW_ON_ERROR),
        ];
    }
}
