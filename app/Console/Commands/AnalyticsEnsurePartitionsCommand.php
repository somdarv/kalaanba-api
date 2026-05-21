<?php

declare(strict_types=1);

namespace App\Console\Commands;

use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Roll forward daily partitions for analytics.events.
 *
 * Idempotent — safe to run on a daily schedule. Creates any missing
 * day-buckets within the configured forward window starting from today.
 *
 * Usage:
 *   php artisan analytics:ensure-partitions
 *   php artisan analytics:ensure-partitions --days=14
 *
 * Ref: docs/Architecture/Build_Plan.md §Phase 0.4
 */
class AnalyticsEnsurePartitionsCommand extends Command
{
    protected $signature = 'analytics:ensure-partitions
                            {--days=7 : Number of forward days (including today) to materialise}';

    protected $description = 'Create any missing daily partitions for analytics.events.';

    public function handle(): int
    {
        $days = max(1, (int) $this->option('days'));
        $today = new DateTimeImmutable('today', new DateTimeZone('UTC'));
        $created = 0;

        for ($offset = 0; $offset < $days; $offset++) {
            $start = $today->modify("+{$offset} days");
            $end = $start->modify('+1 day');
            $partition = $start->format('\\e\\v\\e\\n\\t\\s_\\yY_\\mm_\\dd');

            $exists = DB::selectOne(
                "SELECT 1 AS present
                 FROM pg_class c
                 JOIN pg_namespace n ON n.oid = c.relnamespace
                 WHERE n.nspname = 'analytics' AND c.relname = ?",
                [$partition]
            );

            if ($exists !== null) {
                continue;
            }

            DB::statement(sprintf(
                "CREATE TABLE analytics.%s
                 PARTITION OF analytics.events
                 FOR VALUES FROM ('%s') TO ('%s')",
                $partition,
                $start->format('Y-m-d'),
                $end->format('Y-m-d')
            ));

            $created++;
            $this->line("created analytics.{$partition}");
        }

        $this->line("analytics:ensure-partitions — created {$created} partition(s).");

        return self::SUCCESS;
    }
}
