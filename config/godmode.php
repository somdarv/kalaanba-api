<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| God Mode Admin Panel (ADR-0002, Phase 0.7.5)
|--------------------------------------------------------------------------
|
| Bootstrap configuration for the Filament-powered `/admin` panel. These
| values feed the SuperAdminSeeder and downstream operational toggles.
|
| Engine-owned configuration MUST live in the `admin_config` table (the
| Admin Configuration Engine, Constitution Law 2), not here. This file is
| only for boot-time wiring that exists *before* the database is reachable.
|
*/

return [
    'seed' => [
        'email' => env('GODMODE_SEED_EMAIL'),
        'password' => env('GODMODE_SEED_PASSWORD'),
        'name' => env('GODMODE_SEED_NAME', 'God Mode Operator'),
        'force_reset' => filter_var(env('GODMODE_SEED_FORCE_RESET', false), FILTER_VALIDATE_BOOLEAN),
        'allow_production' => filter_var(env('GODMODE_SEED_ALLOW_PRODUCTION', false), FILTER_VALIDATE_BOOLEAN),
    ],
];
