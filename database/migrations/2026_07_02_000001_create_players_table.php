<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Player & Affiliation Engine — WP-20260702-player-profile.
 *
 * The `players` table: a persistent football identity linked to one Identity
 * account. Engine doc §3 (name model), §4 (identity states), §6 (V1 fields).
 *
 * Constitution: §1.1 (engine boundary — no cross-schema FK to `users`; the
 * link is a bare `user_id` the Identity engine owns), §1.13 (archive, don't
 * delete — `archived_at` instead of hard delete). Status keys are stable
 * snake_case (§1.4); labels live in Admin Config, not here.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('players', function (Blueprint $table): void {
            $table->uuid('id')->primary();

            // Owning Identity account. No FK — cross-schema references are
            // forbidden (Constitution §1.1); Identity owns the `users` table.
            $table->uuid('user_id');

            // Name model (§3). Column sizes sit above the configurable max
            // lengths so a config bump never needs a schema change.
            $table->string('first_name', 120);
            $table->string('last_name', 120);
            $table->string('stage_name', 120);

            $table->unsignedSmallInteger('preferred_number')->nullable();

            // Configurable, evolving sets → varchar keys, not DB enums.
            $table->string('primary_position', 40)->nullable();
            $table->string('availability_status', 20)->default('unknown');
            $table->string('market_status', 20)->default('free_agent');
            $table->string('claim_status', 20)->default('claimed');

            // Separate player-media asset (§7), independent of users.avatar_url.
            $table->text('headshot_url')->nullable();

            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent();
            // Archive, don't delete (Constitution §1.13).
            $table->timestamp('archived_at')->nullable();

            // One player per user in V1 (among non-archived rows).
            $table->unique('user_id');
            // Discovery hot path: free agents by market status.
            $table->index('market_status');
        });

        if (Schema::getConnection()->getDriverName() === 'pgsql') {
            DB::statement(
                "COMMENT ON TABLE players IS 'Owned by the Player & Affiliation engine. A claimed player football identity linked to one Identity account (user_id, no cross-schema FK). Engine doc docs/engines/player-affiliation/.'"
            );
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('players');
    }
};
