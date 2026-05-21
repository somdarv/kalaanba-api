<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Append-only outbox table shared across all engines.
 *
 * The outbox_relay worker is the ONLY consumer that writes delivered_at /
 * attempts / last_error; all other application writes are pure INSERTs.
 *
 * Ref: docs/Architecture/Build_Plan.md §Phase 0.3
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('outbox_events', function (Blueprint $table) {
            // Surrogate PK — time-ordered for hot-path index benefit.
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));

            // The canonical event identity (must be unique and stable).
            $table->uuid('event_id')->unique();

            // Format: engine.action (e.g. match.result_confirmed).
            $table->text('event_name');

            // Increment when payload schema changes in a breaking way.
            $table->smallInteger('schema_version')->default(1);

            // Full canonical envelope payload.
            $table->jsonb('payload');

            // When the domain event occurred (UTC).
            $table->timestampTz('occurred_at');

            // Relay metadata — null until the relay marks the row delivered.
            $table->timestampTz('delivered_at')->nullable();
            $table->integer('attempts')->default(0);
            $table->text('last_error')->nullable();

            // Append-only: created_at only; no updated_at.
            $table->timestampTz('created_at')->default(DB::raw('now()'));
        });

        // Primary relay polling index: undelivered rows first, ordered by age.
        DB::statement(
            'CREATE INDEX outbox_events_relay_idx
             ON outbox_events (delivered_at NULLS FIRST, occurred_at ASC)'
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('outbox_events');
    }
};
