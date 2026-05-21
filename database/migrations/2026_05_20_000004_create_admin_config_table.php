<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('admin_config', static function (Blueprint $table): void {
            $table->id('id');
            $table->string('key', 255)->index();
            $table->string('scope', 50)->default('platform')->index();
            $table->string('scope_id', 36)->nullable()->index(); // UUID as string for compatibility
            $table->text('value');
            $table->timestamp('effective_from')->useCurrent()->index();
            $table->smallInteger('version')->default(1);
            $table->uuid('approved_by')->nullable()->index();
            $table->string('approval_level', 20)->default('low');
            $table->text('change_reason')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrentOnUpdate();

            $table->unique(['key', 'scope', 'scope_id', 'effective_from'], 'admin_config_key_scope_effective_uq');
            $table->index(['key', 'scope', 'scope_id', 'effective_from'], 'admin_config_temporal_idx');
        });

        DB::statement('COMMENT ON TABLE admin_config IS \'Versioned, effective-dated configuration registry. One row per (key, scope, scope_id, effective_from). Enables rollback to past values. Read via Config::get(key, scope, at?).\'');
    }

    public function down(): void
    {
        Schema::dropIfExists('admin_config');
    }
};
