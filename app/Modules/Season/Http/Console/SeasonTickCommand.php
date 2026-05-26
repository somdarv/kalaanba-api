<?php

declare(strict_types=1);

namespace Kalaanba\Modules\Season\Http\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Kalaanba\Modules\Season\Application\SeasonTicker;

/**
 * `php artisan season:tick`
 *
 * Runs one scheduler pass: computes the desired phase for "now", updates
 * the seasons row if it differs, and emits any cutoff events that have
 * just become due. Idempotent — dedupe via deterministic outbox event IDs.
 *
 * Wrapped in a Redis lock so two processes can't race during a 15-minute
 * cron overlap (Constitution §1.14 — idempotent writes).
 *
 * Engine doc: docs/engines/season/Season_Engine_UPDATED.md §11.
 */
final class SeasonTickCommand extends Command
{
    /** @var string */
    protected $signature = 'season:tick';

    /** @var string */
    protected $description = 'Advance the Kalaanba season clock and emit phase/cutoff outbox events.';

    public function handle(SeasonTicker $ticker): int
    {
        $lock = Cache::lock('kx:season:scheduler:v1', 90);

        if (! $lock->get()) {
            $this->info('Another season:tick run is in progress — skipping.');

            return self::SUCCESS;
        }

        try {
            $emitted = $ticker->tick();
        } finally {
            $lock->release();
        }

        foreach ($emitted as $event => $count) {
            $this->line(sprintf('  %-26s %d', $event, $count));
        }

        return self::SUCCESS;
    }
}
