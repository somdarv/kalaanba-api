<?php

declare(strict_types=1);

namespace Kalaanba\Modules\PlayerAffiliation;

use Illuminate\Support\ServiceProvider;
use Kalaanba\Modules\PlayerAffiliation\Domain\AffiliationRepository;
use Kalaanba\Modules\PlayerAffiliation\Domain\PlayerRepository;
use Kalaanba\Modules\PlayerAffiliation\Domain\VerifiedStatsReader;
use Kalaanba\Modules\PlayerAffiliation\Infrastructure\Eloquent\EloquentAffiliationRepository;
use Kalaanba\Modules\PlayerAffiliation\Infrastructure\Eloquent\EloquentPlayerRepository;
use Kalaanba\Modules\PlayerAffiliation\Infrastructure\EmptyVerifiedStats;

/**
 * Service provider for the PlayerAffiliation engine module.
 *
 * Engine doc (canonical): docs/engines/player-affiliation/
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
final class PlayerAffiliationServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(PlayerRepository::class, EloquentPlayerRepository::class);
        $this->app->bind(AffiliationRepository::class, EloquentAffiliationRepository::class);

        // Verified stats (§13). Bound to the empty reader because Match /
        // Fixture has no module yet, so no match anywhere carries
        // `result_confirmed = true` and every player's verified record is
        // genuinely zero. When Match ships it rebinds this one line.
        //
        // This binding is the §13 gate. There is no other route by which a
        // number can reach a player's profile totals.
        $this->app->bind(VerifiedStatsReader::class, EmptyVerifiedStats::class);
    }

    public function boot(): void
    {
        // Load module-scoped routes, migrations, translations, etc.
    }
}
