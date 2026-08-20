<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Club Engine — WP-20260702-club-finder-join (WP-C1).
 *
 * `clubs` (a shared football identity) + `club_memberships` (who controls it).
 * Club creation makes the creator an Owner. Engine doc §2–§5 (identity +
 * creation), §7 (roles), §15 (public profile).
 *
 * Constitution: §1.1 (engine boundary — `city_hub_id` / `area_id` reference the
 * Zone engine and `created_by_user_id` / membership `user_id` reference Identity
 * with NO cross-schema FK; only the same-engine `club_id` FK is enforced),
 * §1.13 (archive, don't delete). Roles/maturity are stable snake_case keys
 * (§1.4); labels live in Admin Config.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('clubs', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('name', 120);
            // Configurable, evolving set → varchar key, not a DB enum.
            $table->string('club_type', 40);
            // Zone-engine references (cross-engine, so no FK — §1.1).
            $table->uuid('city_hub_id');
            $table->uuid('area_id');
            $table->text('crest_url')->nullable();
            $table->string('maturity_level', 20)->default('informal');
            // Identity reference (cross-engine, no FK).
            $table->uuid('created_by_user_id');
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent();
            $table->timestamp('archived_at')->nullable();

            // Discovery hot path: clubs near a player, by area then hub.
            $table->index('area_id');
            $table->index('city_hub_id');
        });

        Schema::create('club_memberships', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('club_id');
            // Identity reference (cross-engine, no FK).
            $table->uuid('user_id');
            $table->string('role', 20);
            $table->string('state', 20)->default('active');
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('archived_at')->nullable();

            // Same-engine FK is allowed and desirable.
            $table->foreign('club_id')->references('id')->on('clubs');
            // One membership row per (club, user) in V1.
            $table->unique(['club_id', 'user_id']);
            $table->index('user_id');
        });

        if (Schema::getConnection()->getDriverName() === 'pgsql') {
            DB::statement(
                "COMMENT ON TABLE clubs IS 'Owned by the Club engine. A shared football identity. city_hub_id/area_id reference the Zone engine and created_by_user_id references Identity with no cross-schema FK (Constitution 1.1).'"
            );
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('club_memberships');
        Schema::dropIfExists('clubs');
    }
};
