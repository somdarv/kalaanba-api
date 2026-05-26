<?php

declare(strict_types=1);

namespace Kalaanba\Modules\Analytics\Schemas;

use Kalaanba\Modules\Analytics\Domain\EventSchema;

/**
 * Emitted when an admin rejects an Area suggestion. The suggestion row
 * is preserved with terminal status (Constitution §1.13).
 */
final class ZoneAreaRejectedSchema
{
    public static function definition(): EventSchema
    {
        return new EventSchema(
            eventName: 'zone.area_rejected',
            schemaVersion: 1,
            requiredProperties: [
                'suggestion_id',
                'reviewed_by_user_id',
                'reviewed_at',
            ],
        );
    }
}
