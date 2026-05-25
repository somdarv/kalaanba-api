<?php

declare(strict_types=1);

namespace App\Providers\Filament;

use App\Http\Middleware\AdminAuditLogger;
use App\Http\Middleware\TouchLastSeenAt;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Pages;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Widgets;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

/**
 * God Mode admin panel — pre-alpha through beta lifetime.
 *
 * - Mounted at `/admin`, session guard `web`.
 * - Access gated by `FilamentUser::canAccessPanel()` on `App\Models\User`
 *   (Super Admin only). See ADR-0002.
 * - Auto-discovers resources/pages/widgets from BOTH `app/Filament/...`
 *   (cross-cutting God Mode surfaces) AND `app/Modules/<Engine>/Filament/...`
 *   (per-engine surfaces; mandatory from Phase 1.1 onward).
 * - Branded with Kalaanba primary `#f55694`.
 */
class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        $panel = $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->login()
            ->brandName('Kalaanba God Mode')
            ->colors([
                'primary' => [
                    50 => '#fff1f6',
                    100 => '#ffe4ee',
                    200 => '#ffc9dd',
                    300 => '#ff9dc1',
                    400 => '#ff6fa3',
                    500 => '#f55694',
                    600 => '#db3a7a',
                    700 => '#b62b62',
                    800 => '#902450',
                    900 => '#6f1d3f',
                    950 => '#430d24',
                ],
            ])
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\\Filament\\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\\Filament\\Pages')
            ->pages([
                Pages\Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\\Filament\\Widgets')
            ->widgets([
                Widgets\AccountWidget::class,
                Widgets\FilamentInfoWidget::class,
            ])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                VerifyCsrfToken::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
                AdminAuditLogger::class,
                TouchLastSeenAt::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ]);

        $this->discoverEngineSurfaces($panel);

        return $panel;
    }

    /**
     * Forward-compatibility hook: every engine module under `app/Modules/`
     * may ship `Filament/Resources/`, `Filament/Pages/`, and `Filament/Widgets/`
     * directories. Adding a new engine automatically surfaces its admin
     * resources in `/admin` with zero panel-provider edits.
     *
     * Namespace convention: `Kalaanba\Modules\<Engine>\Filament\Resources\...`.
     */
    private function discoverEngineSurfaces(Panel $panel): void
    {
        $modulesPath = app_path('Modules');
        if (! is_dir($modulesPath)) {
            return;
        }

        $moduleDirs = glob($modulesPath.DIRECTORY_SEPARATOR.'*', GLOB_ONLYDIR) ?: [];

        foreach ($moduleDirs as $moduleDir) {
            $moduleName = basename($moduleDir);
            $namespaceBase = 'Kalaanba\\Modules\\'.$moduleName.'\\Filament';
            $filamentDir = $moduleDir.DIRECTORY_SEPARATOR.'Filament';

            $resourcesDir = $filamentDir.DIRECTORY_SEPARATOR.'Resources';
            if (is_dir($resourcesDir)) {
                $panel->discoverResources(in: $resourcesDir, for: $namespaceBase.'\\Resources');
            }

            $pagesDir = $filamentDir.DIRECTORY_SEPARATOR.'Pages';
            if (is_dir($pagesDir)) {
                $panel->discoverPages(in: $pagesDir, for: $namespaceBase.'\\Pages');
            }

            $widgetsDir = $filamentDir.DIRECTORY_SEPARATOR.'Widgets';
            if (is_dir($widgetsDir)) {
                $panel->discoverWidgets(in: $widgetsDir, for: $namespaceBase.'\\Widgets');
            }
        }
    }
}
