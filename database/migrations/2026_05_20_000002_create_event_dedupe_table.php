<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Deduplication table for idempotent event consumers.
 *
 * Consumers INSERT a row on first successful processing and check for its
 * existence before acting again. The composite PK makes duplicate INSERTs
 * a no-op at the DB level when combined with insertOrIgnore().
 *
 * Ref: docs/Architecture/Build_Plan.md §Phase 0.3
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('event_dedupe', function (Blueprint $table) {
            // The domain event ID from the canonical envelope.
            $table->uuid('event_id');

            // Fully-qualified listener class name or a stable short name.
            $table->string('listener_name', 255);

            // When this listener successfully processed the event (UTC).
            $table->timestampTz('processed_at')->default(DB::raw('now()'));

            $table->primary(['event_id', 'listener_name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_dedupe');
    }
};
