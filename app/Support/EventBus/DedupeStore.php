<?php

declare(strict_types=1);

namespace Kalaanba\Support\EventBus;

use Illuminate\Support\Facades\DB;

/**
 * Idempotent listener deduplication store.
 *
 * Consumers call isProcessed() before acting, and markProcessed() after a
 * successful run.  Both operations touch only the event_dedupe table.
 *
 * Usage inside a listener:
 *
 *   if ($dedupe->isProcessed($eventId, static::class)) {
 *       return;
 *   }
 *   // … do the work …
 *   $dedupe->markProcessed($eventId, static::class);
 *
 * If the listener itself should be transactional, wrap the work AND the
 * markProcessed() call in a DB::transaction().
 *
 * Ref: engineering-standards §8 — dedupe by (event_id + listener_name)
 */
class DedupeStore
{
    /**
     * Returns true when this listener has already processed the event.
     */
    public function isProcessed(string $eventId, string $listenerName): bool
    {
        return DB::table('event_dedupe')
            ->where('event_id', $eventId)
            ->where('listener_name', $listenerName)
            ->exists();
    }

    /**
     * Records that the listener has successfully processed the event.
     *
     * Uses insertOrIgnore() so a duplicate call (e.g. a retry after a
     * partial failure before markProcessed() returned) is silent.
     */
    public function markProcessed(string $eventId, string $listenerName): void
    {
        DB::table('event_dedupe')->insertOrIgnore([
            'event_id' => $eventId,
            'listener_name' => $listenerName,
            'processed_at' => now()->toDateTimeString(),
        ]);
    }
}
