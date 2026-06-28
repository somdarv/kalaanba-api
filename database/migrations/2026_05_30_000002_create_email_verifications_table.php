<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * WP-20260530 — `email_verifications` table.
 *
 * One row per outstanding email-verify token. Plaintext token is NEVER
 * stored; only a SHA-256 hash. Consumption is recorded by setting
 * `consumed_at` — rows are never deleted (Constitution §1.13).
 *
 * Refs:
 *   - docs/engines/identity/Identity_Engine_System_Document.md §7.1 (email path)
 *   - engineering-standards §11 (no plaintext secrets)
 */
return new class extends Migration
{
    public function up(): void
    {
        $driver = Schema::getConnection()->getDriverName();

        if ($driver !== 'pgsql') {
            Schema::create('email_verifications', function (Blueprint $table): void {
                $table->uuid('id')->primary();
                $table->uuid('user_id');
                $table->string('email', 255);
                $table->string('token_hash', 64);
                $table->string('purpose', 32);
                $table->timestamp('expires_at');
                $table->timestamp('consumed_at')->nullable();
                $table->timestamp('created_at')->useCurrent();
                $table->index('user_id', 'email_verifications_user_idx');
                $table->index('token_hash', 'email_verifications_token_idx');
            });

            return;
        }

        DB::statement(<<<'SQL'
            CREATE TABLE email_verifications (
                id           UUID         NOT NULL DEFAULT gen_random_uuid() PRIMARY KEY,
                user_id      UUID         NOT NULL,
                email        VARCHAR(255) NOT NULL,
                token_hash   VARCHAR(64)  NOT NULL,
                purpose      VARCHAR(32)  NOT NULL,
                expires_at   TIMESTAMPTZ  NOT NULL,
                consumed_at  TIMESTAMPTZ,
                created_at   TIMESTAMPTZ  NOT NULL DEFAULT now(),
                CONSTRAINT email_verifications_purpose_check
                    CHECK (purpose IN ('registration', 'bind_email'))
            )
        SQL);

        DB::statement(
            'CREATE UNIQUE INDEX email_verifications_token_hash_unique
             ON email_verifications (token_hash)'
        );
        DB::statement(
            'CREATE INDEX email_verifications_user_pending_idx
             ON email_verifications (user_id)
             WHERE consumed_at IS NULL'
        );
        DB::statement(
            'COMMENT ON TABLE email_verifications IS '
            ."'Identity engine. Plaintext token NEVER persisted — only SHA-256 hash. "
            ."Row is never deleted; consumption sets consumed_at (Constitution §1.13).'"
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('email_verifications');
    }
};
