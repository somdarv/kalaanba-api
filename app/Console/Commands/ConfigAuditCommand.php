<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Integrity check for the configuration registry.
 *
 * Exists because of a bug that ran silently for months. `AdminConfigSeeder`
 * wrote with `insertOrIgnore`, but the unique index is
 * (key, scope, scope_id, effective_from) and `effective_from` is "now" on every
 * run, so no two runs ever collided and nothing was ever ignored. Every seeder
 * run inserted a complete second copy of every key. A local database had
 * reached 190 rows for 53 keys.
 *
 * Nothing broke, and that is the point: `Config::get` takes the newest row, the
 * copies were identical, so every read returned the right answer while the
 * table quietly tripled. The only way to catch that class of fault is to look
 * for it on purpose.
 *
 * Run as the last step of `scripts/deploy.sh`. Exits 1 on any finding, so a
 * deploy that corrupts the registry fails loudly instead of looking fine.
 *
 * Ref: docs/engines/admin-governance/
 */
class ConfigAuditCommand extends Command
{
    protected $signature = 'config:audit {--json : Machine-readable output}';

    protected $description = 'Check admin_config for duplicate and malformed rows';

    public function handle(): int
    {
        $duplicates = $this->duplicateGroups();
        $conflicts = array_values(array_filter(
            $duplicates,
            static fn (object $group): bool => (int) $group->distinct_values > 1,
        ));
        $harmless = count($duplicates) - count($conflicts);

        $malformed = $this->malformedJson();

        $totals = DB::table('admin_config')
            ->selectRaw('count(*) AS rows, count(distinct key) AS keys')
            ->first();

        if ($this->option('json')) {
            $this->line((string) json_encode([
                'rows' => (int) ($totals->rows ?? 0),
                'keys' => (int) ($totals->keys ?? 0),
                'duplicate_groups' => count($duplicates),
                'conflicting_groups' => count($conflicts),
                'malformed_json' => count($malformed),
            ]));

            return $duplicates === [] && $malformed === [] ? self::SUCCESS : self::FAILURE;
        }

        $this->info(sprintf(
            'admin_config: %d rows across %d keys.',
            (int) ($totals->rows ?? 0),
            (int) ($totals->keys ?? 0),
        ));

        if ($duplicates === [] && $malformed === []) {
            $this->info('No duplicate or malformed rows. Registry is clean.');

            return self::SUCCESS;
        }

        if ($harmless > 0) {
            $this->warn(sprintf(
                '%d key/version group(s) have repeated rows with identical values. Harmless to reads, but they should not exist: collapse to the earliest row of each group.',
                $harmless,
            ));
        }

        foreach ($conflicts as $group) {
            $this->error(sprintf(
                'CONFLICT: %s v%d has %d rows with %d DIFFERENT values. A read picks one by effective_from. Resolve by hand.',
                $group->key,
                (int) $group->version,
                (int) $group->n,
                (int) $group->distinct_values,
            ));
        }

        foreach ($malformed as $row) {
            $this->error(sprintf(
                'MALFORMED: %s v%d holds a value that is neither valid JSON nor a scalar.',
                $row->key,
                (int) $row->version,
            ));
        }

        return self::FAILURE;
    }

    /**
     * Rows sharing a (key, scope, scope_id, version). One row per group is the
     * only correct state: a real change appends a NEW version, never a second
     * row at the same one.
     *
     * @return list<object>
     */
    private function duplicateGroups(): array
    {
        return DB::select(
            "SELECT key, version, count(*) AS n, count(distinct value) AS distinct_values
             FROM admin_config
             GROUP BY key, scope, COALESCE(scope_id, ''), version
             HAVING count(*) > 1
             ORDER BY count(*) DESC, key",
        );
    }

    /**
     * A value that starts like JSON but will not decode. Catches a truncated or
     * hand-edited option set before an engine reads it and silently falls back
     * to its compiled default.
     *
     * @return list<object>
     */
    private function malformedJson(): array
    {
        $suspects = DB::select(
            "SELECT key, version, value FROM admin_config
             WHERE value LIKE '{%' OR value LIKE '[%'",
        );

        return array_values(array_filter(
            $suspects,
            static fn (object $row): bool => json_decode($row->value, true) === null,
        ));
    }
}
