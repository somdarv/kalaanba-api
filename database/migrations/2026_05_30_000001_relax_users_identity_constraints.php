<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * WP-20260530 — Identity Engine self-signup constraints.
 *
 * Relaxes the `users` table so that exactly ONE of (email, phone) is
 * required at row creation time, then re-tightens uniqueness using
 * partial indexes filtered on `archived_at IS NULL` — matching the
 * engine doc §6 rule that an archived phone/email may be re-registered.
 *
 * Refs:
 *   - docs/engines/identity/Identity_Engine_System_Document.md §6, §7, §8
 *   - Constitution §1.13 (archive don't delete) + §1.11 (no in-place
 *     destructive mutation that hides history).
 *
 * Rollback note: down() restores NOT NULL on email/password and the
 * original full unique indexes. Safe ONLY when no rows have NULL email
 * or NULL password — otherwise rollback requires a data backfill.
 */
return new class extends Migration
{
    public function up(): void
    {
        $driver = Schema::getConnection()->getDriverName();

        // 1. Drop the old full-table unique indexes (they will be replaced
        //    by partial indexes filtered on archived_at IS NULL).
        if ($driver === 'pgsql') {
            // Both were created via Blueprint $table->unique(), which on
            // Postgres backs each with a UNIQUE CONSTRAINT (the matching
            // index can only be dropped via DROP CONSTRAINT).
            DB::statement('ALTER TABLE users DROP CONSTRAINT IF EXISTS users_email_unique');
            DB::statement('ALTER TABLE users DROP CONSTRAINT IF EXISTS users_phone_e164_hash_unique');
        } else {
            Schema::table('users', function (Blueprint $table): void {
                $table->dropUnique('users_email_unique');
                $table->dropUnique('users_phone_e164_hash_unique');
            });
        }

        // 2. Relax email + password to nullable. Add claimed_at.
        Schema::table('users', function (Blueprint $table): void {
            $table->string('email')->nullable()->change();
            $table->string('password')->nullable()->change();
            $table->timestamp('claimed_at')->nullable()->after('archived_at');
        });

        // 3. Re-add uniqueness as PARTIAL indexes (Postgres) or normal
        //    unique indexes (SQLite — partials not supported broadly there).
        if ($driver === 'pgsql') {
            DB::statement(<<<'SQL'
                CREATE UNIQUE INDEX users_email_active_unique
                    ON users (email)
                    WHERE archived_at IS NULL AND email IS NOT NULL
            SQL);

            DB::statement(<<<'SQL'
                CREATE UNIQUE INDEX users_phone_e164_hash_active_unique
                    ON users (phone_e164_hash)
                    WHERE archived_at IS NULL AND phone_e164_hash IS NOT NULL
            SQL);

            // 4. Channel invariant (engine doc §8).
            DB::statement(<<<'SQL'
                ALTER TABLE users
                ADD CONSTRAINT users_channel_present_check
                CHECK (phone_e164_hash IS NOT NULL OR email IS NOT NULL)
            SQL);
        } else {
            Schema::table('users', function (Blueprint $table): void {
                $table->unique('email', 'users_email_active_unique');
                $table->unique('phone_e164_hash', 'users_phone_e164_hash_active_unique');
            });
        }
    }

    public function down(): void
    {
        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'pgsql') {
            DB::statement('ALTER TABLE users DROP CONSTRAINT IF EXISTS users_channel_present_check');
            DB::statement('DROP INDEX IF EXISTS users_email_active_unique');
            DB::statement('DROP INDEX IF EXISTS users_phone_e164_hash_active_unique');
        } else {
            Schema::table('users', function (Blueprint $table): void {
                $table->dropUnique('users_email_active_unique');
                $table->dropUnique('users_phone_e164_hash_active_unique');
            });
        }

        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn('claimed_at');
            $table->string('password')->nullable(false)->change();
            $table->string('email')->nullable(false)->change();
            $table->unique('email', 'users_email_unique');
            $table->unique('phone_e164_hash', 'users_phone_e164_hash_unique');
        });
    }
};
