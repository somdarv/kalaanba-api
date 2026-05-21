<?php

declare(strict_types=1);

namespace Kalaanba\Modules\AdminGovernance;

use Illuminate\Support\ServiceProvider;
use Kalaanba\Modules\AdminGovernance\Domain\Contracts\ConfigRepository;
use Kalaanba\Modules\AdminGovernance\Infrastructure\PostgresConfigRepository;
use Kalaanba\Support\Config\Contracts\ConfigRepository as SharedConfigRepository;

/**
 * Service provider for the AdminGovernance engine module.
 *
 * Engine doc (canonical): docs/engines/admin-governance/
 * Engine boundary rules:  docs/engine-boundaries.md
 * Layering rules:         app/Modules/README.md
 *
 * Responsibilities:
 *  - Bind the ConfigRepository (Postgres + Redis) for admin config reads/writes.
 *  - Load this module's routes from Http/routes.php (when present).
 *  - Register this module's event subscribers / listeners.
 *
 * MUST NOT:
 *  - Reach into another module's namespace directly.
 *  - Bypass the outbox for cross-engine effects.
 */
final class AdminGovernanceServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Bind the shared interface to the implementation
        $this->app->bind(
            SharedConfigRepository::class,
            PostgresConfigRepository::class,
        );

        // Create an alias so AdminGovernance ConfigRepository resolves to the shared one
        $this->app->alias(SharedConfigRepository::class, ConfigRepository::class);
    }

    public function boot(): void
    {
        // Load module-scoped routes, migrations, translations, etc.
    }
}
