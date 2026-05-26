<?php

declare(strict_types=1);

namespace Kalaanba\Modules\Analytics\Schemas;

use Kalaanba\Modules\Analytics\Domain\EventSchema;

/**
 * Emitted when a user submits a new Area suggestion for a City Hub.
 *
 * Engine doc: docs/engines/zone/ §5; Constitution §1.5 (audited),
 * §1.6 (event-first), §1.10 (admin-controlled hierarchy).
 */
final class ZoneAreaSuggestedSchema
{
    public static function definition(): EventSchema
    {
        return new EventSchema(
            eventName: 'zone.area_suggested',
            schemaVersion: 1,
            requiredProperties: [
                'suggestion_id',
                'city_hub_id',
                'proposed_name',
                'submitted_by_user_id',
                'submitted_at',
            ],
        );
    }
}
