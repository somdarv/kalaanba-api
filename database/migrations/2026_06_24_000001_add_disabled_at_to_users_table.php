<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Soft, reversible account disable — distinct from `archived_at` (soft-delete,
 * constitution Law 13). A disabled account cannot log in but is trivially
 * re-enabled. Used by the admin Users support tools (WP-20260624).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->timestamp('disabled_at')->nullable()->after('archived_at');
            $table->index(['disabled_at'], 'users_disabled_at_index');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropIndex('users_disabled_at_index');
            $table->dropColumn('disabled_at');
        });
    }
};
