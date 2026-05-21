<?php

declare(strict_types=1);

namespace App\Console\Commands;

use DateTimeImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Kalaanba\Support\EventBus\OutboxEnvelope;
use Kalaanba\Support\EventBus\RedisStreamPublisher;
use RuntimeException;

/**
 * Outbox relay — polls undelivered rows and publishes them to Redis Streams.
 *
 * Run as a long-lived supervisor process:
 *   php artisan outbox:relay --sleep=1
 *
 * Or as a one-shot drain (CI / health checks):
 *   php artisan outbox:relay --once
 *
 * Ref: docs/Architecture/Build_Plan.md §Phase 0.3
 */
class OutboxRelayCommand extends Command
{
    protected $signature = 'outbox:relay
                            {--limit=100    : Max rows to process per poll cycle}
                            {--sleep=1      : Seconds to sleep between cycles (float allowed)}
                            {--once         : Drain one batch then exit}
                            {--max-attempts=5 : Skip rows that have failed this many times}';

    protected $description = 'Relay undelivered outbox events to Redis Streams.';

    public function handle(): int
    {
        $limit = (int) $this->option('limit');
        $sleep = (float) $this->option('sleep');
        $once = (bool) $this->option('once');
        $maxAttempts = (int) $this->option('max-attempts');

        $publisher = $this->laravel->make(RedisStreamPublisher::class);

        do {
            $processed = $this->drainBatch($publisher, $limit, $maxAttempts);

            if ($once) {
                $this->line("outbox:relay — drained {$processed} row(s).");

                return self::SUCCESS;
            }

            if ($processed === 0) {
                usleep((int) ($sleep * 1_000_000));
            }
        } while (true);
    }

    /**
     * Fetch up to $limit undelivered rows and publish each one.
     *
     * @return int Number of rows successfully delivered in this cycle.
     */
    private function drainBatch(
        RedisStreamPublisher $publisher,
        int $limit,
        int $maxAttempts,
    ): int {
        $rows = DB::table('outbox_events')
            ->whereNull('delivered_at')
            ->where('attempts', '<', $maxAttempts)
            ->orderBy('occurred_at')
            ->limit($limit)
            ->get();

        $delivered = 0;

        foreach ($rows as $row) {
            try {
                $envelope = $this->hydrateEnvelope($row);
                $publisher->publish($envelope);

                DB::table('outbox_events')
                    ->where('id', $row->id)
                    ->update([
                        'delivered_at' => now()->toDateTimeString(),
                        'attempts' => $row->attempts + 1,
                        'last_error' => null,
                    ]);

                $delivered++;
            } catch (\Throwable $e) {
                DB::table('outbox_events')
                    ->where('id', $row->id)
                    ->update([
                        'attempts' => $row->attempts + 1,
                        'last_error' => mb_substr($e->getMessage(), 0, 2000),
                    ]);

                $this->error(
                    "outbox:relay — failed row {$row->id} "
                    ."(attempt {$row->attempts}): {$e->getMessage()}"
                );
            }
        }

        return $delivered;
    }

    /**
     * Re-hydrate an OutboxEnvelope from a raw DB row.
     *
     * @param  object  $row  stdClass from DB::table()->get()
     *
     * @throws RuntimeException when the stored payload cannot be decoded.
     */
    private function hydrateEnvelope(object $row): OutboxEnvelope
    {
        $data = json_decode($row->payload, true, 512, JSON_THROW_ON_ERROR);

        return new OutboxEnvelope(
            eventId: $row->event_id,
            eventName: $row->event_name,
            schemaVersion: (int) $row->schema_version,
            occurredAt: new DateTimeImmutable($row->occurred_at),
            actorId: $data['actor_id'] ?? null,
            actorRole: $data['actor_role'] ?? null,
            source: $data['source'] ?? $row->event_name,
            payload: $data['payload'] ?? [],
        );
    }
}
