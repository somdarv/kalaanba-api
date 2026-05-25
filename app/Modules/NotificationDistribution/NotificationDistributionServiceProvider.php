<?php

declare(strict_types=1);

namespace Kalaanba\Modules\NotificationDistribution;

use Illuminate\Support\ServiceProvider;
use Kalaanba\Modules\NotificationDistribution\Domain\InboxRepository;
use Kalaanba\Modules\NotificationDistribution\Infrastructure\Eloquent\PostgresInboxRepository;

/**
 * Service provider for the NotificationDistribution engine module.
 *
 * Engine doc (canonical): docs/engines/notification-distribution/
 * Engine boundary rules:  docs/engine-boundaries.md
 * Layering rules:         app/Modules/README.md
 *
 * Responsibilities:
 *  - Bind Domain interfaces (ports) to Infrastructure adapters.
 *  - Load this module's routes from Http/routes.php (when present).
 *  - Register this module's event subscribers / listeners.
 *
 * MUST NOT:
 *  - Reach into another module's namespace directly.
 *  - Bypass the outbox for cross-engine effects.
 */
final class NotificationDistributionServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(InboxRepository::class, PostgresInboxRepository::class);
    }

    public function boot(): void
    {
        // Load module-scoped routes, migrations, translations, etc.
    }
}
