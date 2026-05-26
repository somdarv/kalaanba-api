<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Zone Engine — Phase 1.2 WP.
 *
 * Creates the hierarchy spine: Country → Region → City Hub → Zone (kind
 * = zone | belt) → Area, plus an audited Area-suggestion workflow.
 *
 * Engine doc: docs/engines/zone/Zone_Engine_UPDATED.md §§2, 5.
 * Constitution: §1.1 (engine boundaries — other engines read only via the
 * GeographyReader port), §1.10 (admin-controlled hierarchy), §1.13 (archive
 * do not delete — suggestions are append-only with terminal status).
 */
return new class extends Migration
{
    private const ZONE_KINDS = ['zone', 'belt'];

    private const SUGGESTION_STATUSES = ['pending', 'approved', 'rejected'];

    public function up(): void
    {
        $isPg = Schema::getConnection()->getDriverName() === 'pgsql';

        if ($isPg) {
            $this->createEnum('zone_kind', self::ZONE_KINDS);
            $this->createEnum('area_suggestion_status', self::SUGGESTION_STATUSES);
        }

        Schema::create('countries', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('code', 2)->unique();   // ISO-3166-1 alpha-2
            $table->string('name', 100);
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent();
        });

        Schema::create('regions', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('country_id');
            $table->string('code', 32);
            $table->string('name', 120);
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent();
            $table->unique(['country_id', 'code']);
            $table->foreign('country_id')->references('id')->on('countries');
        });

        Schema::create('city_hubs', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('region_id');
            $table->string('code', 64);
            $table->string('name', 120);
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent();
            $table->unique(['region_id', 'code']);
            $table->foreign('region_id')->references('id')->on('regions');
        });

        if ($isPg) {
            DB::statement(<<<'SQL'
                CREATE TABLE zones (
                    id           UUID       NOT NULL DEFAULT gen_random_uuid() PRIMARY KEY,
                    city_hub_id  UUID       NOT NULL REFERENCES city_hubs(id),
                    kind         zone_kind  NOT NULL,
                    code         VARCHAR(64) NOT NULL,
                    name         VARCHAR(120) NOT NULL,
                    created_at   TIMESTAMPTZ NOT NULL DEFAULT now(),
                    updated_at   TIMESTAMPTZ NOT NULL DEFAULT now(),
                    UNIQUE (city_hub_id, code)
                )
            SQL);
            DB::statement('CREATE INDEX zones_city_hub_kind_idx ON zones (city_hub_id, kind)');
        } else {
            Schema::create('zones', function (Blueprint $table): void {
                $table->uuid('id')->primary();
                $table->uuid('city_hub_id');
                $table->string('kind', 8);
                $table->string('code', 64);
                $table->string('name', 120);
                $table->timestamp('created_at')->useCurrent();
                $table->timestamp('updated_at')->useCurrent();
                $table->unique(['city_hub_id', 'code']);
                $table->index(['city_hub_id', 'kind']);
                $table->foreign('city_hub_id')->references('id')->on('city_hubs');
            });
        }

        Schema::create('areas', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('zone_id');
            $table->string('code', 64);
            $table->string('name', 120);
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent();
            $table->unique(['zone_id', 'code']);
            $table->foreign('zone_id')->references('id')->on('zones');
        });

        if ($isPg) {
            DB::statement(<<<'SQL'
                CREATE TABLE area_suggestions (
                    id                    UUID       NOT NULL DEFAULT gen_random_uuid() PRIMARY KEY,
                    city_hub_id           UUID       NOT NULL REFERENCES city_hubs(id),
                    proposed_zone_id      UUID       REFERENCES zones(id),
                    proposed_name         VARCHAR(120) NOT NULL,
                    note                  TEXT,
                    submitted_by_user_id  UUID       NOT NULL,
                    status                area_suggestion_status NOT NULL DEFAULT 'pending',
                    reviewed_by_user_id   UUID,
                    review_note           TEXT,
                    resulting_area_id     UUID       REFERENCES areas(id),
                    submitted_at          TIMESTAMPTZ NOT NULL DEFAULT now(),
                    reviewed_at           TIMESTAMPTZ
                )
            SQL);
            DB::statement('CREATE INDEX area_suggestions_status_idx ON area_suggestions (status, submitted_at)');
            DB::statement(
                "COMMENT ON TABLE area_suggestions IS 'Owned by the Zone engine. Append-only audit trail of user Area suggestions and admin review decisions (Constitution Sec 1.5, 1.13).'"
            );
        } else {
            Schema::create('area_suggestions', function (Blueprint $table): void {
                $table->uuid('id')->primary();
                $table->uuid('city_hub_id');
                $table->uuid('proposed_zone_id')->nullable();
                $table->string('proposed_name', 120);
                $table->text('note')->nullable();
                $table->uuid('submitted_by_user_id');
                $table->string('status', 16)->default('pending');
                $table->uuid('reviewed_by_user_id')->nullable();
                $table->text('review_note')->nullable();
                $table->uuid('resulting_area_id')->nullable();
                $table->timestamp('submitted_at')->useCurrent();
                $table->timestamp('reviewed_at')->nullable();
                $table->index(['status', 'submitted_at']);
                $table->foreign('city_hub_id')->references('id')->on('city_hubs');
                $table->foreign('proposed_zone_id')->references('id')->on('zones');
                $table->foreign('resulting_area_id')->references('id')->on('areas');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('area_suggestions');
        Schema::dropIfExists('areas');
        Schema::dropIfExists('zones');
        Schema::dropIfExists('city_hubs');
        Schema::dropIfExists('regions');
        Schema::dropIfExists('countries');

        if (Schema::getConnection()->getDriverName() === 'pgsql') {
            DB::statement('DROP TYPE IF EXISTS area_suggestion_status');
            DB::statement('DROP TYPE IF EXISTS zone_kind');
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
