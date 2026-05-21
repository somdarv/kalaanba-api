<?php

declare(strict_types=1);

namespace Kalaanba\Support\EventBus;

use Illuminate\Support\Facades\DB;

/**
 * Writes an OutboxEnvelope to the outbox_events table.
 *
 * MUST be called inside the same DB::transaction() as the domain write.
 * The caller is responsible for wrapping both the domain write and this
 * call in a transaction — OutboxWriter does NOT open one itself.
 *
 * Example:
 *   DB::transaction(function () use ($writer, $envelope) {
 *       $domainRepo->save($aggregate);
 *       $writer->write($envelope);
 *   });
 *
 * Ref: engineering-standards §8 — outbox in same transaction as domain write
 */
class OutboxWriter
{
    public function write(OutboxEnvelope $envelope): void
    {
        DB::table('outbox_events')->insert([
            'event_id' => $envelope->eventId,
            'event_name' => $envelope->eventName,
            'schema_version' => $envelope->schemaVersion,
            'payload' => json_encode($envelope->toPayloadArray(), JSON_THROW_ON_ERROR),
            'occurred_at' => $envelope->occurredAt->format('Y-m-d H:i:s.uP'),
            'created_at' => now()->toDateTimeString(),
        ]);
    }
}
