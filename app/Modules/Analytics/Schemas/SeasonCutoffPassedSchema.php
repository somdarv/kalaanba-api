<?php

declare(strict_types=1);

namespace Kalaanba\Modules\Analytics\Schemas;

use Kalaanba\Modules\Analytics\Domain\EventSchema;

/**
 * Emitted when a configurable cutoff is crossed inside the active
 * season — participation cutoff, new-ranked-challenge cutoff, ranked
 * acceptance cutoff. Downstream engines (Challenge, RP Economy, Awards)
 * subscribe to halt or finalise their flows.
 *
 * Required payload:
 *   - season_id, season_code
 *   - cutoff_key: stable internal key, e.g. `participation_cutoff_at`
 *   - cutoff_at: ISO-8601 UTC timestamp the cutoff was scheduled for
 *
 * Engine doc: docs/engines/season/Season_Engine_UPDATED.md §9 + §12.
 */
final class SeasonCutoffPassedSchema
{
    public static function definition(): EventSchema
    {
        return new EventSchema(
            eventName: 'season.cutoff_passed',
            schemaVersion: 1,
            requiredProperties: ['season_id', 'season_code', 'cutoff_key', 'cutoff_at'],
        );
    }
}
