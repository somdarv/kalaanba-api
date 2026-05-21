<?php

declare(strict_types=1);

namespace Kalaanba\Modules\VenueSurfaceBooking;

use Illuminate\Support\ServiceProvider;

/**
 * Service provider for the VenueSurfaceBooking engine module.
 *
 * Engine doc (canonical): docs/engines/venue-surface-booking/
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
final class VenueSurfaceBookingServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Bind Domain ports to Infrastructure adapters here.
    }

    public function boot(): void
    {
        // Load module-scoped routes, migrations, translations, etc.
    }
}
