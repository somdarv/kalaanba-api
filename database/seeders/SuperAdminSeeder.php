<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Hash;
use Kalaanba\Support\Auth\Role;
use RuntimeException;

/**
 * Seed the first Super Admin so the God Mode `/admin` panel has a real
 * operator on a fresh install (ADR-0002, Phase 0.7.5).
 *
 * Idempotent: re-running this seeder updates the existing user's role/password
 * only when explicitly forced via `godmode.seed.force_reset=true`. Otherwise
 * an existing row is left untouched.
 *
 * Hard-fails in `production` environment unless `godmode.seed.allow_production=true`
 * — the seeder is a dev/staging tool. Constitution L1 (engine boundaries) is
 * respected: this seeder only touches `users`, never engine schemas.
 *
 * Configuration sourced from {@see config/godmode.php}:
 *  - godmode.seed.email             (required)
 *  - godmode.seed.password          (required, min 12 in production, min 8 elsewhere)
 *  - godmode.seed.name              (default "God Mode Operator")
 *  - godmode.seed.force_reset       (default false)
 *  - godmode.seed.allow_production  (default false)
 */
class SuperAdminSeeder extends Seeder
{
    public function run(): void
    {
        /** @var string|null $email */
        $email = config('godmode.seed.email');
        /** @var string|null $password */
        $password = config('godmode.seed.password');
        /** @var string $name */
        $name = config('godmode.seed.name', 'God Mode Operator');
        /** @var bool $forceReset */
        $forceReset = (bool) config('godmode.seed.force_reset', false);
        /** @var bool $allowProduction */
        $allowProduction = (bool) config('godmode.seed.allow_production', false);

        if ($email === null || $email === '' || $password === null || $password === '') {
            $this->log('warn', 'SuperAdminSeeder: godmode.seed.email and godmode.seed.password must be set. Skipping.');

            return;
        }

        if (App::environment('production') && ! $allowProduction) {
            throw new RuntimeException(
                'SuperAdminSeeder refuses to run in production without godmode.seed.allow_production=true. '
                .'See ADR-0002 — God Mode is a pre-alpha/alpha/beta tool.'
            );
        }

        $minPasswordLength = App::environment('production') ? 12 : 8;
        if (mb_strlen($password) < $minPasswordLength) {
            throw new RuntimeException(
                "SuperAdminSeeder: godmode.seed.password must be at least {$minPasswordLength} characters in this environment."
            );
        }

        /** @var User|null $existing */
        $existing = User::query()->where('email', $email)->first();

        if ($existing !== null && ! $forceReset) {
            if ($existing->role !== Role::SuperAdmin) {
                $this->log('warn', "SuperAdminSeeder: user {$email} exists with role {$existing->role->value}; not promoting (set godmode.seed.force_reset=true to override).");
            } else {
                $this->log('info', "SuperAdminSeeder: SuperAdmin {$email} already exists. Nothing to do.");
            }

            return;
        }

        if ($existing !== null) {
            $existing->forceFill([
                'name' => $name,
                'password' => Hash::make($password),
                'role' => Role::SuperAdmin,
                'archived_at' => null,
            ])->save();

            $this->log('info', "SuperAdminSeeder: reset {$email} to SuperAdmin (force-reset enabled).");

            return;
        }

        $user = new User;
        $user->forceFill([
            'name' => $name,
            'email' => $email,
            'password' => Hash::make($password),
            'role' => Role::SuperAdmin,
            'email_verified_at' => now(),
        ])->save();

        $this->log('info', "SuperAdminSeeder: created SuperAdmin {$user->email} (id={$user->id}).");
    }

    /**
     * Bridge over Laravel's seeder-output API — `$this->command` is null
     * when the seeder runs outside of the artisan console (e.g. tests via
     * `$this->seed()`). Larastan thinks the property is always set, so we
     * route through this guarded helper to keep static analysis happy.
     */
    private function log(string $level, string $message): void
    {
        $command = $this->command;
        if ($command === null) {
            return;
        }

        match ($level) {
            'warn' => $command->warn($message),
            'info' => $command->info($message),
            default => $command->line($message),
        };
    }
}
