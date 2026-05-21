<?php

declare(strict_types=1);

namespace Kalaanba\Modules\Analytics;

use Illuminate\Contracts\Container\Container;
use Illuminate\Database\DatabaseManager;
use Illuminate\Support\ServiceProvider;
use Kalaanba\Modules\Analytics\Application\AnalyticsEmitter;
use Kalaanba\Modules\Analytics\Domain\Contracts\AnalyticsEventWriter;
use Kalaanba\Modules\Analytics\Domain\EventSchemaRegistry;
use Kalaanba\Modules\Analytics\Infrastructure\DatabaseAnalyticsEventWriter;
use Kalaanba\Modules\Analytics\Schemas\SchemaCatalogue;

/**
 * Service provider for the Analytics engine module.
 *
 * Engine doc (canonical): docs/engines/analytics/
 * Engine boundary rules:  docs/engine-boundaries.md
 * Layering rules:         app/Modules/README.md
 *
 * Responsibilities:
 *  - Bind the schema registry as a singleton populated from SchemaCatalogue.
 *  - Bind AnalyticsEventWriter to the database adapter.
 *  - Register the AnalyticsEmitter for application use.
 *
 * MUST NOT:
 *  - Reach into another module's namespace directly.
 *  - Bypass the outbox for cross-engine effects.
 */
final class AnalyticsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(EventSchemaRegistry::class, function (): EventSchemaRegistry {
            $registry = new EventSchemaRegistry;
            SchemaCatalogue::registerAll($registry);

            return $registry;
        });

        $this->app->bind(
            AnalyticsEventWriter::class,
            static function (Container $app): AnalyticsEventWriter {
                /** @var DatabaseManager $db */
                $db = $app->make('db');

                return new DatabaseAnalyticsEventWriter($db->connection());
            }
        );

        $this->app->bind(AnalyticsEmitter::class, static function (Container $app): AnalyticsEmitter {
            return new AnalyticsEmitter(
                $app->make(EventSchemaRegistry::class),
                $app->make(AnalyticsEventWriter::class),
            );
        });
    }

    public function boot(): void
    {
        // Module-scoped routes / listeners land here per WP.
    }
}
