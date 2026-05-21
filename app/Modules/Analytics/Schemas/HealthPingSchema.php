<?php

declare(strict_types=1);

namespace Kalaanba\Modules\Analytics\Schemas;

use Kalaanba\Modules\Analytics\Domain\EventSchema;

/**
 * Canonical Phase 0 schema — emitted by the health:ping artisan command to
 * prove the analytics pipeline is wired end-to-end.
 *
 * Required:
 *   - ping_id: stable id of the ping (uuid string)
 *
 * Ref: docs/Architecture/Build_Plan.md §Phase 0.3 (event bus), §Phase 0.4 (analytics).
 */
final class HealthPingSchema
{
    public static function definition(): EventSchema
    {
        return new EventSchema(
            eventName: 'health.ping',
            schemaVersion: 1,
            requiredProperties: ['ping_id'],
        );
    }
}
