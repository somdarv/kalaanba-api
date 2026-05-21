<?php

declare(strict_types=1);

namespace App\Console\Commands;

use DateTimeImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Kalaanba\Support\EventBus\OutboxEnvelope;
use Kalaanba\Support\EventBus\OutboxWriter;

/**
 * End-to-end health probe for the event bus.
 *
 * 1. Creates a "health.ping" OutboxEnvelope.
 * 2. Writes it to outbox_events inside a DB transaction.
 * 3. Delegates to outbox:relay --once to publish it to Redis Streams.
 * 4. Exits 0 on success, 1 on any failure.
 *
 * Intended for local dev verification and smoke tests.
 * NOT for production health-check endpoints — use the /up route for that.
 *
 * Ref: docs/Architecture/Build_Plan.md §Phase 0.3
 */
class HealthPingCommand extends Command
{
    protected $signature = 'health:ping';

    protected $description = 'Fire a health.ping event end-to-end through the outbox and Redis Streams.';

    public function handle(OutboxWriter $writer): int
    {
        $eventId = (string) Str::uuid();

        $envelope = new OutboxEnvelope(
            eventId: $eventId,
            eventName: 'health.ping',
            schemaVersion: 1,
            occurredAt: new DateTimeImmutable,
            actorId: null,
            actorRole: 'system',
            source: 'health',
            payload: ['ping_id' => $eventId],
        );

        DB::transaction(function () use ($writer, $envelope): void {
            $writer->write($envelope);
        });

        $this->info("health:ping — event {$eventId} written to outbox.");

        // Run the relay in one-shot mode.
        $this->call('outbox:relay', ['--once' => true]);

        // Verify delivery by checking the specific row — relay exit 0 alone is
        // not sufficient because the relay returns SUCCESS even when rows fail.
        $delivered = DB::table('outbox_events')
            ->where('event_id', $eventId)
            ->whereNotNull('delivered_at')
            ->exists();

        if (! $delivered) {
            $row = DB::table('outbox_events')->where('event_id', $eventId)->first();
            $error = $row !== null ? (string) $row->last_error : 'row not found';

            $this->error("health:ping — event was NOT delivered to Redis. Error: {$error}");

            return self::FAILURE;
        }

        $this->info('health:ping — event confirmed delivered to Redis Streams. Bus is healthy.');

        return self::SUCCESS;
    }
}
