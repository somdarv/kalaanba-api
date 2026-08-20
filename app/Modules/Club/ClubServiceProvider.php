<?php

declare(strict_types=1);

namespace Kalaanba\Modules\Club;

use Illuminate\Support\ServiceProvider;
use Kalaanba\Modules\Club\Domain\ClubReader;
use Kalaanba\Modules\Club\Domain\ClubRepository;
use Kalaanba\Modules\Club\Infrastructure\Eloquent\EloquentClubStore;

/**
 * Service provider for the Club engine module.
 *
 * Engine doc (canonical): docs/engines/club/
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
final class ClubServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(EloquentClubStore::class);
        $this->app->bind(ClubRepository::class, EloquentClubStore::class);
        $this->app->bind(ClubReader::class, EloquentClubStore::class);
    }

    public function boot(): void
    {
        // Load module-scoped routes, migrations, translations, etc.
    }
}
