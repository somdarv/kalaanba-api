<?php

declare(strict_types=1);

namespace Kalaanba\Modules\Zone;

use Illuminate\Support\ServiceProvider;
use Kalaanba\Modules\Zone\Domain\AreaSuggestionRepository;
use Kalaanba\Modules\Zone\Domain\GeographyReader;
use Kalaanba\Modules\Zone\Infrastructure\Eloquent\EloquentAreaSuggestionRepository;
use Kalaanba\Modules\Zone\Infrastructure\Eloquent\EloquentGeographyReader;

/**
 * Service provider for the Zone engine module.
 *
 * Engine doc (canonical): docs/engines/zone/
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
final class ZoneServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(GeographyReader::class, EloquentGeographyReader::class);
        $this->app->bind(AreaSuggestionRepository::class, EloquentAreaSuggestionRepository::class);
    }

    public function boot(): void
    {
        // Load module-scoped routes, migrations, translations, etc.
    }
}
