<?php

declare(strict_types=1);

namespace Kalaanba\Modules\Analytics\Domain;

use Kalaanba\Modules\Analytics\Domain\Exceptions\UnknownEventSchemaException;

/**
 * In-memory registry of every analytics event schema known to the platform.
 *
 * Populated at boot by AnalyticsServiceProvider from the Schemas/ folder.
 * Lookups are deterministic and side-effect-free.
 *
 * Ref: docs/engines/analytics/Analytics_Insights_Intelligence_Engine_System_Document.md §11
 */
final class EventSchemaRegistry
{
    /** @var array<string, EventSchema> keyed by `<event_name>@v<version>` */
    private array $schemas = [];

    public function register(EventSchema $schema): void
    {
        $this->schemas[$schema->key()] = $schema;
    }

    /**
     * @throws UnknownEventSchemaException when no schema matches the (name, version) pair.
     */
    public function require(string $eventName, int $schemaVersion): EventSchema
    {
        $key = $eventName.'@v'.$schemaVersion;

        if (! isset($this->schemas[$key])) {
            throw new UnknownEventSchemaException(
                "No analytics event schema registered for \"{$key}\"."
            );
        }

        return $this->schemas[$key];
    }

    public function has(string $eventName, int $schemaVersion): bool
    {
        return isset($this->schemas[$eventName.'@v'.$schemaVersion]);
    }

    /** @return list<EventSchema> */
    public function all(): array
    {
        return array_values($this->schemas);
    }
}
