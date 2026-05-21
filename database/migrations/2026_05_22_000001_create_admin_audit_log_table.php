<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Append-only admin audit log. Partitioned monthly by occurred_at on Postgres
 * so old months can be detached for archival without rewriting hot rows.
 *
 * Ref: docs/Architecture/Build_Plan.md §0.6 WP-C, Constitution Law 5.
 *
 * NEVER write to this table from domain code — only AdminAuditMiddleware.
 * Application role MUST NOT have UPDATE/DELETE on this table (grant managed
 * in scripts/setup-postgres.sql — append at deploy time).
 */
return new class extends Migration
{
    public function up(): void
    {
        $driver = Schema::getConnection()->getDriverName();

        if ($driver !== 'pgsql') {
            Schema::create('admin_audit_log', function ($table): void {
                $table->uuid('id')->primary();
                $table->string('actor_id', 64)->index();
                $table->string('actor_role', 40);
                $table->string('request_id', 64)->index();
                $table->string('route', 255)->nullable();
                $table->string('method', 10);
                $table->string('path', 2048);
                $table->smallInteger('response_status');
                $table->json('payload_redacted');
                $table->timestamp('occurred_at')->useCurrent()->index();
            });

            return;
        }

        DB::statement(<<<'SQL'
            CREATE TABLE admin_audit_log (
                id               UUID         NOT NULL DEFAULT gen_random_uuid(),
                actor_id         VARCHAR(64)  NOT NULL,
                actor_role       VARCHAR(40)  NOT NULL,
                request_id       VARCHAR(64)  NOT NULL,
                route            VARCHAR(255),
                method           VARCHAR(10)  NOT NULL,
                path             VARCHAR(2048) NOT NULL,
                response_status  SMALLINT     NOT NULL,
                payload_redacted JSONB        NOT NULL DEFAULT '{}'::jsonb,
                occurred_at      TIMESTAMPTZ  NOT NULL DEFAULT now(),
                PRIMARY KEY (id, occurred_at)
            ) PARTITION BY RANGE (occurred_at);
        SQL);

        // Default partition catches any row whose month has no explicit
        // partition yet. Ops will pre-create monthly partitions via cron
        // and detach old ones for archival.
        DB::statement(
            'CREATE TABLE admin_audit_log_default PARTITION OF admin_audit_log DEFAULT'
        );

        DB::statement(
            'CREATE INDEX admin_audit_log_occurred_idx ON admin_audit_log (occurred_at DESC)'
        );
        DB::statement(
            'CREATE INDEX admin_audit_log_actor_idx ON admin_audit_log (actor_id, occurred_at DESC)'
        );
        DB::statement(
            'CREATE INDEX admin_audit_log_request_idx ON admin_audit_log (request_id)'
        );

        DB::statement(
            'COMMENT ON TABLE admin_audit_log IS '
            ."'Append-only audit trail of every authenticated write performed by a platform admin. "
            ."Written exclusively by AdminAuditMiddleware. Partitioned monthly by occurred_at.'"
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('admin_audit_log_default');
        Schema::dropIfExists('admin_audit_log');
    }
};
