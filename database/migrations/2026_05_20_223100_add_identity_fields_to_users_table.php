<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Kalaanba\Support\Auth\Role;

return new class extends Migration
{
    public function up(): void
    {
        $roleValues = array_map(static fn (Role $role): string => $role->value, Role::cases());
        $roleCheck = "'".implode("','", $roleValues)."'";

        Schema::table('users', function (Blueprint $table): void {
            // Platform role. Stored as varchar + CHECK (no native enum)
            // to keep additive role rollouts cheap; the CHECK constraint
            // mirrors Kalaanba\Support\Auth\Role exactly (see arch test).
            $table->string('role', 32)
                ->default(Role::default()->value)
                ->after('email');

            // Phone identity — stored as a one-way hash for lookup +
            // last-4 digits for support/display. Plaintext phone is NEVER
            // persisted (engineering-standards §11, constitution Law 5).
            $table->string('phone_e164_hash', 64)->nullable()->after('role');
            $table->string('phone_e164_last4', 4)->nullable()->after('phone_e164_hash');

            // Archive-don't-delete (constitution Law 13). Active filter
            // drives token issuance + most read endpoints.
            $table->timestamp('archived_at')->nullable()->after('updated_at');
            $table->timestamp('last_seen_at')->nullable()->after('archived_at');

            $table->unique('phone_e164_hash', 'users_phone_e164_hash_unique');
            $table->index(['role', 'archived_at'], 'users_role_archived_at_index');
        });

        // CHECK constraint on the role column. Postgres-only; the test
        // suite runs on Postgres (phpunit.xml leaves DB_CONNECTION at the
        // project default).
        if (Schema::getConnection()->getDriverName() === 'pgsql') {
            Schema::getConnection()->statement(
                "ALTER TABLE users ADD CONSTRAINT users_role_check CHECK (role IN ($roleCheck))"
            );
        }
    }

    public function down(): void
    {
        if (Schema::getConnection()->getDriverName() === 'pgsql') {
            Schema::getConnection()->statement('ALTER TABLE users DROP CONSTRAINT IF EXISTS users_role_check');
        }

        Schema::table('users', function (Blueprint $table): void {
            $table->dropUnique('users_phone_e164_hash_unique');
            $table->dropIndex('users_role_archived_at_index');
            $table->dropColumn([
                'role',
                'phone_e164_hash',
                'phone_e164_last4',
                'archived_at',
                'last_seen_at',
            ]);
        });
    }
};
