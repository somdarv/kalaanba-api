<?php

declare(strict_types=1);

namespace Kalaanba\Modules\Season;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\ServiceProvider;
use Kalaanba\Modules\Season\Domain\SeasonConfigProvider;
use Kalaanba\Modules\Season\Domain\SeasonRepository;
use Kalaanba\Modules\Season\Http\Console\SeasonTickCommand;
use Kalaanba\Modules\Season\Infrastructure\Config\AdminConfigSeasonConfigLoader;
use Kalaanba\Modules\Season\Infrastructure\Eloquent\EloquentSeasonRepository;

/**
 * Service provider for the Season engine module.
 *
 * Engine doc (canonical): docs/engines/season/
 * Engine boundary rules:  docs/engine-boundaries.md
 * Layering rules:         app/Modules/README.md
 *
 * Responsibilities:
 *  - Bind Domain interfaces (ports) to Infrastructure adapters.
 *  - Register the season:tick artisan command + 15-minute schedule.
 *
 * MUST NOT:
 *  - Reach into another module's namespace directly.
 *  - Bypass the outbox for cross-engine effects.
 */
final class SeasonServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(SeasonRepository::class, EloquentSeasonRepository::class);
        $this->app->bind(SeasonConfigProvider::class, AdminConfigSeasonConfigLoader::class);
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([SeasonTickCommand::class]);

            $this->app->booted(function (): void {
                /** @var Schedule $schedule */
                $schedule = $this->app->make(Schedule::class);
                $schedule->command('season:tick')
                    ->everyFifteenMinutes()
                    ->withoutOverlapping()
                    ->runInBackground();
            });
        }
    }
}
