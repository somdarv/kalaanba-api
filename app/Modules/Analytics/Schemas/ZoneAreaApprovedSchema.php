<?php

declare(strict_types=1);

namespace Kalaanba\Modules\Analytics\Schemas;

use Kalaanba\Modules\Analytics\Domain\EventSchema;

/**
 * Emitted when an admin approves an Area suggestion and it is promoted
 * into the hierarchy under the chosen Zone.
 */
final class ZoneAreaApprovedSchema
{
    public static function definition(): EventSchema
    {
        return new EventSchema(
            eventName: 'zone.area_approved',
            schemaVersion: 1,
            requiredProperties: [
                'suggestion_id',
                'area_id',
                'zone_id',
                'name',
                'reviewed_by_user_id',
                'reviewed_at',
            ],
        );
    }
}
