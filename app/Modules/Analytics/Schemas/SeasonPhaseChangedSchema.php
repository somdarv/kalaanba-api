<?php

declare(strict_types=1);

namespace Kalaanba\Modules\Analytics\Schemas;

use Kalaanba\Modules\Analytics\Domain\EventSchema;

/**
 * Emitted when a season transitions between phases (e.g. active → peak,
 * peak → run_in, run_in → closing, closing → archived).
 *
 * Required payload:
 *   - season_id, season_code: identify the season
 *   - from_phase, to_phase: stable SeasonPhase keys (Constitution §1.4)
 *   - occurred_at: ISO-8601 UTC timestamp of the boundary
 *
 * Engine doc: docs/engines/season/Season_Engine_UPDATED.md §2 + §11.
 */
final class SeasonPhaseChangedSchema
{
    public static function definition(): EventSchema
    {
        return new EventSchema(
            eventName: 'season.phase_changed',
            schemaVersion: 1,
            requiredProperties: ['season_id', 'season_code', 'from_phase', 'to_phase', 'occurred_at'],
        );
    }
}
