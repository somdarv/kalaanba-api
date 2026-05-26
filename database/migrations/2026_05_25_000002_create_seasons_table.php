<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Season Engine — Phase 1.1 WP.
 *
 * The `seasons` table is the timing and eligibility spine. One row per
 * platform season (April 1 → Feb 28/29 + March archive window). Other
 * engines NEVER write here — they consume `season.*` outbox events.
 *
 * Refs:
 *   - docs/engines/season/Season_Engine_UPDATED.md §2 (calendar), §12 (defaults)
 *   - docs/Architecture/Build_Plan.md §Phase 1.1
 *   - Constitution §1.1 (engine boundaries), §1.13 (archive don't delete)
 */
return new class extends Migration
{
    /** Engine doc §2 + SeasonPhase enum. */
    private const PHASES = ['preseason', 'active', 'peak', 'run_in', 'closing', 'archived'];

    public function up(): void
    {
        $driver = Schema::getConnection()->getDriverName();

        if ($driver !== 'pgsql') {
            // SQLite fallback so unit tests can spin up without Postgres.
            Schema::create('seasons', function (Blueprint $table): void {
                $table->uuid('id')->primary();
                $table->string('code', 16)->unique();
                $table->timestamp('starts_at');
                $table->timestamp('ends_at');
                $table->timestamp('participation_cutoff_at');
                $table->timestamp('closing_window_ends_at');
                $table->timestamp('archive_window_ends_at');
                $table->string('phase', 16);
                $table->json('key_dates');
                $table->timestamp('archived_at')->nullable();
                $table->timestamp('created_at')->useCurrent();
                $table->timestamp('updated_at')->useCurrent();
            });

            return;
        }

        $this->createEnum('season_phase', self::PHASES);

        DB::statement(<<<'SQL'
            CREATE TABLE seasons (
                id                       UUID         NOT NULL DEFAULT gen_random_uuid() PRIMARY KEY,
                code                     VARCHAR(16)  NOT NULL UNIQUE,
                starts_at                TIMESTAMPTZ  NOT NULL,
                ends_at                  TIMESTAMPTZ  NOT NULL,
                participation_cutoff_at  TIMESTAMPTZ  NOT NULL,
                closing_window_ends_at   TIMESTAMPTZ  NOT NULL,
                archive_window_ends_at   TIMESTAMPTZ  NOT NULL,
                phase                    season_phase NOT NULL DEFAULT 'preseason',
                key_dates                JSONB        NOT NULL DEFAULT '{}'::jsonb,
                archived_at              TIMESTAMPTZ,
                created_at               TIMESTAMPTZ  NOT NULL DEFAULT now(),
                updated_at               TIMESTAMPTZ  NOT NULL DEFAULT now()
            )
        SQL);

        DB::statement('CREATE INDEX seasons_phase_idx ON seasons (phase)');
        DB::statement('CREATE INDEX seasons_window_idx ON seasons (starts_at, archive_window_ends_at)');
        DB::statement(
            'COMMENT ON TABLE seasons IS '
            ."'Owned by the Season engine. Other engines read via the Season::current() port — "
            ."NEVER join across schemas (Constitution §1.1). Archive, do not delete (§1.13).'"
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('seasons');

        if (Schema::getConnection()->getDriverName() === 'pgsql') {
            DB::statement('DROP TYPE IF EXISTS season_phase');
        }
    }

    /**
     * @param  array<string>  $values
     */
    private function createEnum(string $name, array $values): void
    {
        $literals = collect($values)->map(fn (string $v) => "'{$v}'")->implode(', ');
        DB::statement("DO \$\$ BEGIN
            IF NOT EXISTS (SELECT 1 FROM pg_type WHERE typname = '{$name}') THEN
                CREATE TYPE {$name} AS ENUM ({$literals});
            END IF;
        END \$\$;");
    }
};
