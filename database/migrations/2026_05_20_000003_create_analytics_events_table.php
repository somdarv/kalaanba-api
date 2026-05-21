<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Append-only analytics event log, daily partitioned by occurred_at.
 *
 * Owned by the Analytics, Insights & Intelligence Engine.
 * Source of truth: docs/engines/analytics/Analytics_Insights_Intelligence_Engine_System_Document.md
 *   §7 (event-first architecture), §9 (standard payload), §10 (standard fields).
 *
 * The shared `public` schema is the home of cross-cutting plumbing
 * (outbox_events, event_dedupe). The analytics schema is created here
 * because EnsureSchemasCommand has not landed yet (Phase 0.2 follow-up).
 *
 * Ref: docs/Architecture/Build_Plan.md §Phase 0.4
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('CREATE SCHEMA IF NOT EXISTS analytics');

        DB::statement(<<<'SQL'
            CREATE TABLE IF NOT EXISTS analytics.events (
                id              uuid        NOT NULL DEFAULT gen_random_uuid(),
                event_id        uuid        NOT NULL,
                event_name      text        NOT NULL,
                schema_version  smallint    NOT NULL DEFAULT 1,
                occurred_at     timestamptz NOT NULL,
                actor_user_id   uuid        NULL,
                actor_role      text        NULL,
                source          text        NOT NULL,
                session_id      text        NULL,
                device_id       text        NULL,
                route           text        NULL,
                context         jsonb       NOT NULL DEFAULT '{}'::jsonb,
                properties      jsonb       NOT NULL DEFAULT '{}'::jsonb,
                received_at     timestamptz NOT NULL DEFAULT now(),
                PRIMARY KEY (id, occurred_at)
            ) PARTITION BY RANGE (occurred_at)
        SQL);

        // Dedupe by event_id within a partition (events_id is globally unique
        // but Postgres partitioning requires the partition key in unique idx).
        DB::statement(
            'CREATE UNIQUE INDEX IF NOT EXISTS analytics_events_event_id_uq
             ON analytics.events (event_id, occurred_at)'
        );

        // Hot-read indexes propagated to every partition.
        DB::statement(
            'CREATE INDEX IF NOT EXISTS analytics_events_name_occurred_idx
             ON analytics.events (event_name, occurred_at DESC)'
        );

        DB::statement(
            'CREATE INDEX IF NOT EXISTS analytics_events_actor_occurred_idx
             ON analytics.events (actor_user_id, occurred_at DESC)
             WHERE actor_user_id IS NOT NULL'
        );

        // Catch-all partition for late-arriving events outside the materialised window.
        DB::statement(
            'CREATE TABLE IF NOT EXISTS analytics.events_default PARTITION OF analytics.events DEFAULT'
        );
    }

    public function down(): void
    {
        DB::statement('DROP TABLE IF EXISTS analytics.events CASCADE');
        // Keep the schema in place — other engines may already live in it.
    }
};
