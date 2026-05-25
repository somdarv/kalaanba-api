<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Notification & Distribution Engine — Phase 2.5 WP-1.
 *
 * The in-app inbox: persistent record of every notification the platform owes
 * a user. WP-2 will populate this table from outbox event listeners; this
 * migration only stands up the table + indexes + supporting enum types so
 * the read endpoints (and a trivial application-side writer for tests) can
 * land first.
 *
 * Refs:
 *   - docs/engines/notification-distribution/Notification_Distribution_Engine_System_Document.md §13 (lifecycle), §14 (event model), §28 (audit log)
 *   - docs/Architecture/Build_Plan.md §Phase 2.5 (in-app inbox table + endpoints)
 *   - engineering-standards §6 (schema discipline)
 */
return new class extends Migration
{
    /**
     * Lifecycle statuses — see engine doc §13. `created` is reserved for the
     * brief moment between INSERT and outbox handoff; in WP-1 every row that
     * lands in the inbox starts in `delivered` (the in-app channel has no
     * separate send step).
     */
    private const STATUSES = [
        'created',
        'queued',
        'sent',
        'delivered',
        'seen',
        'acted_on',
        'expired',
        'cancelled',
        'failed',
    ];

    /** Engine doc §10 — urgency drives channel + reminder downstream. */
    private const URGENCIES = ['info', 'normal', 'important', 'urgent', 'critical'];

    /**
     * Source categories — engine doc §11. Stable internal keys; UI labels are
     * configurable elsewhere.
     */
    private const CATEGORIES = [
        'match', 'challenge', 'trust', 'referee', 'competition',
        'rp', 'player', 'admin', 'system',
    ];

    public function up(): void
    {
        $driver = Schema::getConnection()->getDriverName();

        // SQLite-only fallback so unit tests can run without Postgres. Production
        // and feature tests use Postgres and the enum types below.
        if ($driver !== 'pgsql') {
            Schema::create('notification_inbox', function (Blueprint $table): void {
                $table->uuid('id')->primary();
                $table->unsignedBigInteger('recipient_user_id')->index();
                $table->string('template_key', 128);
                $table->string('category', 32);
                $table->string('urgency', 16);
                $table->string('status', 16);
                $table->string('title', 255);
                $table->text('body');
                $table->string('action_url', 2048)->nullable();
                $table->string('source_type', 64);
                $table->string('source_id', 64)->nullable();
                $table->json('payload')->nullable();
                $table->timestamp('created_at')->useCurrent();
                $table->timestamp('seen_at')->nullable();
                $table->timestamp('acted_on_at')->nullable();
                $table->timestamp('expires_at')->nullable();
                $table->timestamp('archived_at')->nullable();
            });

            return;
        }

        $this->createEnum('notification_inbox_status', self::STATUSES);
        $this->createEnum('notification_inbox_urgency', self::URGENCIES);
        $this->createEnum('notification_inbox_category', self::CATEGORIES);

        DB::statement(<<<'SQL'
            CREATE TABLE notification_inbox (
                id                 UUID         NOT NULL DEFAULT gen_random_uuid() PRIMARY KEY,
                recipient_user_id  BIGINT       NOT NULL REFERENCES users(id) ON DELETE CASCADE,
                template_key       VARCHAR(128) NOT NULL,
                category           notification_inbox_category NOT NULL,
                urgency            notification_inbox_urgency  NOT NULL DEFAULT 'normal',
                status             notification_inbox_status   NOT NULL DEFAULT 'delivered',
                title              VARCHAR(255) NOT NULL,
                body               TEXT         NOT NULL,
                action_url         VARCHAR(2048),
                source_type        VARCHAR(64)  NOT NULL,
                source_id          VARCHAR(64),
                payload            JSONB        NOT NULL DEFAULT '{}'::jsonb,
                created_at         TIMESTAMPTZ  NOT NULL DEFAULT now(),
                seen_at            TIMESTAMPTZ,
                acted_on_at        TIMESTAMPTZ,
                expires_at         TIMESTAMPTZ,
                archived_at        TIMESTAMPTZ
            )
        SQL);

        // Inbox listing — newest first per recipient, excluding archived rows.
        DB::statement(
            'CREATE INDEX notification_inbox_recipient_idx
             ON notification_inbox (recipient_user_id, created_at DESC, id DESC)
             WHERE archived_at IS NULL'
        );

        // Unread badge query — partial index keeps it tiny.
        DB::statement(
            "CREATE INDEX notification_inbox_unread_idx
             ON notification_inbox (recipient_user_id)
             WHERE archived_at IS NULL
               AND status NOT IN ('seen', 'acted_on', 'expired', 'cancelled')"
        );

        // Sweeper / expiry job lookup.
        DB::statement(
            'CREATE INDEX notification_inbox_expires_idx
             ON notification_inbox (expires_at)
             WHERE expires_at IS NOT NULL AND archived_at IS NULL'
        );

        DB::statement(
            "COMMENT ON TABLE notification_inbox IS "
            ."'In-app notification records owned by the Notification & Distribution engine. "
            ."Each row represents one delivery owed to one user. Archive, never delete (Constitution Law 13).'"
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_inbox');

        if (Schema::getConnection()->getDriverName() === 'pgsql') {
            DB::statement('DROP TYPE IF EXISTS notification_inbox_status');
            DB::statement('DROP TYPE IF EXISTS notification_inbox_urgency');
            DB::statement('DROP TYPE IF EXISTS notification_inbox_category');
        }
    }

    /**
     * @param  list<string>  $values
     */
    private function createEnum(string $name, array $values): void
    {
        $quoted = implode(', ', array_map(
            static fn (string $v): string => "'".$v."'",
            $values,
        ));
        // Idempotent: `migrate:fresh` drops tables but not custom Postgres
        // types, so re-running this migration in the same DB (common in
        // Pest's RefreshDatabase across test classes) would otherwise raise
        // "type already exists". Wrap in DO/EXCEPTION to swallow that.
        DB::statement(<<<SQL
            DO \$\$ BEGIN
                CREATE TYPE {$name} AS ENUM ({$quoted});
            EXCEPTION WHEN duplicate_object THEN null;
            END \$\$;
        SQL);
    }
};
