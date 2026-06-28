<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;
use Kalaanba\Support\Auth\Role;

/**
 * Rebuilds the `users_role_check` constraint so it includes the new universal
 * default role `user` (Identity Engine doc §9). On a fresh migrate the earlier
 * `2026_05_20_223100` migration already derives the constraint from
 * Role::cases(); this migration brings already-migrated dev/prod databases up to
 * the same shape without editing a merged migration (engineering-standards §6).
 *
 * Postgres-only — the test suite and prod run on Postgres; SQLite has no CHECK
 * round-trip to maintain here.
 */
return new class extends Migration
{
    public function up(): void
    {
        $this->rebuildRoleCheck(Role::cases());
    }

    public function down(): void
    {
        // Restore the pre-`user` catalogue.
        $previous = array_values(array_filter(
            Role::cases(),
            static fn (Role $role): bool => $role !== Role::User,
        ));

        $this->rebuildRoleCheck($previous);
    }

    /**
     * @param  list<Role>  $roles
     */
    private function rebuildRoleCheck(array $roles): void
    {
        if (Schema::getConnection()->getDriverName() !== 'pgsql') {
            return;
        }

        $values = array_map(static fn (Role $role): string => $role->value, $roles);
        $roleCheck = "'".implode("','", $values)."'";

        Schema::getConnection()->statement('ALTER TABLE users DROP CONSTRAINT IF EXISTS users_role_check');
        Schema::getConnection()->statement(
            "ALTER TABLE users ADD CONSTRAINT users_role_check CHECK (role IN ($roleCheck))"
        );
    }
};
