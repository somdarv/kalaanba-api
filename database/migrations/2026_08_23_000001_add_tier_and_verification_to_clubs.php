<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Club Engine — WP-20260823-club-creation.
 *
 * Adds the tier a club was created through and where it stands with
 * verification (engine doc §4, §9, §10; ADR-0017).
 *
 * Both columns land in one migration on purpose, against the one-concern rule:
 * they are a single concept split across two fields, and the partial unique
 * index below needs both to exist before it can be written. Splitting them
 * would mean altering a hot table twice for one change.
 *
 * `verification_state = 'pending'` marks a club that claimed the `professional`
 * tier and has not been checked. It bears a name it has not yet earned, so it
 * is hidden from every public read until an admin clears it. That filter lives
 * in one private query builder inside EloquentClubStore rather than at each
 * call site.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clubs', function (Blueprint $table): void {
            // Configurable, evolving sets → varchar keys, not DB enums (§1.4).
            $table->string('tier', 20)->default('amateur')->after('club_type');
            $table->string('verification_state', 20)->default('not_required')->after('maturity_level');
            $table->string('verification_source', 30)->nullable()->after('verification_state');
        });

        if (Schema::getConnection()->getDriverName() !== 'pgsql') {
            // SQLite (test suite) has no partial indexes worth the trouble; the
            // uniqueness below is enforced in Postgres, which is what runs.
            return;
        }

        // Only pending claims are worth indexing: the column is 'not_required'
        // for almost every row, so a full index would be dead weight on writes.
        DB::statement(
            "CREATE INDEX clubs_pending_verification_idx ON clubs (verification_state) WHERE verification_state = 'pending'"
        );

        // Two people must not hold a claim on the same name at once. Compared
        // on a lowercased, whitespace-collapsed name so "Asante  Kotoko" and
        // "asante kotoko" collide. This is a coarser normalisation than
        // ClubNamePolicy's — it cannot drop ignored tokens without a stored
        // column — and that is fine: it is a backstop against a duplicate
        // claim, not the name policy itself.
        DB::statement(
            "CREATE UNIQUE INDEX clubs_pending_claim_name_unique
                ON clubs (lower(regexp_replace(name, '\s+', ' ', 'g')))
                WHERE verification_state = 'pending' AND archived_at IS NULL"
        );

        DB::statement(
            "COMMENT ON COLUMN clubs.verification_state IS 'not_required | pending | cleared | rejected. A pending club is hidden from every public read (ADR-0017). RP, Challenge and Fan Buzz must read this before acting on a club.'"
        );
    }

    public function down(): void
    {
        if (Schema::getConnection()->getDriverName() === 'pgsql') {
            DB::statement('DROP INDEX IF EXISTS clubs_pending_claim_name_unique');
            DB::statement('DROP INDEX IF EXISTS clubs_pending_verification_idx');
        }

        Schema::table('clubs', function (Blueprint $table): void {
            $table->dropColumn(['tier', 'verification_state', 'verification_source']);
        });
    }
};
