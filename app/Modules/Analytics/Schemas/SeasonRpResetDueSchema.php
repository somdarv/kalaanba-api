<?php

declare(strict_types=1);

namespace Kalaanba\Modules\Analytics\Schemas;

use Kalaanba\Modules\Analytics\Domain\EventSchema;

/**
 * Emitted at archive boundary. The RP Economy engine consumes this to
 * reset Season RP balances (Constitution §1.11 — RP mutated only via
 * ledger entries — RP Economy writes the compensating ledger entries).
 *
 * Required payload:
 *   - season_id, season_code: the season being archived
 *   - next_season_code: the new season that has begun
 *   - occurred_at: ISO-8601 UTC timestamp of the archive boundary
 *
 * Engine doc: docs/engines/season/Season_Engine_UPDATED.md §7 + §11.
 */
final class SeasonRpResetDueSchema
{
    public static function definition(): EventSchema
    {
        return new EventSchema(
            eventName: 'season.rp_reset_due',
            schemaVersion: 1,
            requiredProperties: ['season_id', 'season_code', 'next_season_code', 'occurred_at'],
        );
    }
}
