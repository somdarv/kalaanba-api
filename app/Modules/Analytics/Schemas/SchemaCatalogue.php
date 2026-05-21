<?php

declare(strict_types=1);

namespace Kalaanba\Modules\Analytics\Schemas;

use Kalaanba\Modules\Analytics\Domain\EventSchema;
use Kalaanba\Modules\Analytics\Domain\EventSchemaRegistry;

/**
 * Single enumeration of every analytics event schema in the platform.
 *
 * Add a new schema by:
 *   1. Creating a *Schema class with a public static `definition()` returning EventSchema.
 *   2. Appending it to the all() list below.
 *   3. Updating contracts/events/<engine>/<event_name>.yaml.
 *
 * The arch test in tests/Architecture forces every registered schema to be
 * surfaced here, so payload drift fails CI loudly.
 */
final class SchemaCatalogue
{
    /** @return list<EventSchema> */
    public static function all(): array
    {
        return [
            HealthPingSchema::definition(),
        ];
    }

    public static function registerAll(EventSchemaRegistry $registry): void
    {
        foreach (self::all() as $schema) {
            $registry->register($schema);
        }
    }
}
