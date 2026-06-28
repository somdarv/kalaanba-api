<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Admin access codes — short shared secrets that a platform admin must
 * re-enter to confirm destructive Users-section actions (set password,
 * force-verify, delete). Stored ONLY as a bcrypt hash — the plaintext code
 * is never persisted or returned (WP-20260624, ADR-0005).
 *
 * `label` namespaces a code to a surface (e.g. `users_section`) so codes can
 * be rotated independently.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('admin_access_codes', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('label', 64)->unique();
            $table->string('code_hash');
            $table->timestamp('last_used_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admin_access_codes');
    }
};
