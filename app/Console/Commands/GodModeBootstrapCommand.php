<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Database\Seeders\SuperAdminSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\App;

/**
 * Interactive bootstrap for the God Mode `/admin` panel (ADR-0002, Phase 0.7.5).
 *
 * Replaces the brittle "set env vars + run db:seed" two-step. Operators run a
 * single command:
 *
 *   php artisan godmode:bootstrap
 *
 * …and are prompted for the email, password, and optional name. The values
 * are pushed into runtime config and {@see SuperAdminSeeder} runs against
 * that in-memory state, so this never relies on PowerShell env-var
 * propagation surviving into the artisan child process.
 *
 * Non-interactive use (CI, scripts) is supported via options:
 *
 *   php artisan godmode:bootstrap --email=ops@kalaanba.local --password=... --force-reset
 *
 * Constitution L1 is preserved: this command writes only to `users`, never
 * to engine schemas. Constitution L5 (audit) is N/A here because there is
 * no actor before the first SuperAdmin is bootstrapped.
 */
class GodModeBootstrapCommand extends Command
{
    protected $signature = 'godmode:bootstrap
                            {--email= : Super Admin email address}
                            {--password= : Super Admin password (min 8 dev / 12 prod)}
                            {--name= : Display name (default: God Mode Operator)}
                            {--force-reset : Reset password/role for an existing user with this email}
                            {--allow-production : Permit bootstrapping in the production environment}';

    protected $description = 'Bootstrap the first Super Admin for the /admin God Mode panel (ADR-0002).';

    public function handle(): int
    {
        $email = $this->option('email') ?: $this->ask('Super Admin email');
        if (! is_string($email) || $email === '') {
            $this->error('Email is required.');

            return self::INVALID;
        }

        $nameOption = $this->option('name');
        $name = is_string($nameOption) && $nameOption !== '' ? $nameOption : 'God Mode Operator';

        $password = $this->option('password');
        if (! is_string($password) || $password === '') {
            $password = $this->secret('Password (input hidden)');
            if (! is_string($password) || $password === '') {
                $this->error('Password is required.');

                return self::INVALID;
            }

            $confirm = $this->secret('Confirm password');
            if ($password !== $confirm) {
                $this->error('Passwords do not match.');

                return self::INVALID;
            }
        }

        $forceReset = (bool) $this->option('force-reset');
        $allowProduction = (bool) $this->option('allow-production');

        config()->set('godmode.seed', [
            'email' => $email,
            'password' => $password,
            'name' => $name,
            'force_reset' => $forceReset,
            'allow_production' => $allowProduction,
        ]);

        if (App::environment('production') && ! $allowProduction) {
            $this->error('Refusing to run in production without --allow-production.');

            return self::FAILURE;
        }

        $this->info("Bootstrapping Super Admin <{$email}>…");

        try {
            $this->call('db:seed', ['--class' => SuperAdminSeeder::class, '--force' => true]);
        } catch (\Throwable $e) {
            $this->error('SuperAdminSeeder failed: '.$e->getMessage());

            return self::FAILURE;
        }

        $this->newLine();
        $this->info('Done. Visit /admin/login and sign in.');

        return self::SUCCESS;
    }
}
