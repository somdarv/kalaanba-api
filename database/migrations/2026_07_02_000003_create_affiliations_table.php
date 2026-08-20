<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Player & Affiliation Engine — WP-20260702-club-finder-join (WP-C2).
 *
 * `affiliations`: the relationship between a player and a club (engine doc §8).
 * V1 flow: player requests (`requested`) → club Owner/Admin accepts (`active`)
 * or declines (`declined`). Multi-club allowed (§9); one affiliation row per
 * (player, club).
 *
 * Constitution: §1.1 (engine boundary — `player_id` is a same-engine FK;
 * `club_id` references the Club engine with NO cross-schema FK), §1.5 (audited
 * transitions via decided_by + timestamps), §1.13 (archive, don't delete).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('affiliations', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            // Same-engine reference — FK allowed and desirable.
            $table->uuid('player_id');
            // Club engine reference (cross-engine, no FK — §1.1).
            $table->uuid('club_id');
            $table->string('state', 20)->default('requested');
            $table->uuid('requested_by_user_id');
            $table->uuid('decided_by_user_id')->nullable();
            $table->timestamp('requested_at')->useCurrent();
            $table->timestamp('decided_at')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent();
            $table->timestamp('archived_at')->nullable();

            $table->foreign('player_id')->references('id')->on('players');
            // One affiliation row per (player, club).
            $table->unique(['player_id', 'club_id']);
            // Club-side pending-requests hot path.
            $table->index(['club_id', 'state']);
        });

        if (Schema::getConnection()->getDriverName() === 'pgsql') {
            DB::statement(
                "COMMENT ON TABLE affiliations IS 'Owned by the Player & Affiliation engine. Player<->club relationship with a request->accept lifecycle. club_id references the Club engine with no cross-schema FK (Constitution 1.1).'"
            );
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('affiliations');
    }
};
