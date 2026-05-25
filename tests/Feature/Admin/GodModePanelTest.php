<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Kalaanba\Support\Auth\Role;

uses(RefreshDatabase::class);

/**
 * God Mode Panel routes smoke test (Slice 2).
 *
 * Asserts that the 7 Filament resources are mounted on /admin/* and that
 * the gate behaves correctly:
 *   - unauthenticated → redirect to /admin/login
 *   - non-SuperAdmin  → 403
 *   - SuperAdmin      → 200
 */

/**
 * @return array<int, string>
 */
function godModePanelRoutes(): array
{
    return [
        '/admin/users',
        '/admin/outbox-events',
        '/admin/admin-audit-logs',
        '/admin/admin-configs',
        '/admin/analytics-events',
        '/admin/personal-access-tokens',
        '/admin/event-dedupes',
    ];
}

it('redirects anonymous visitors to login on every admin resource route', function (string $route): void {
    $this->get($route)->assertRedirect('/admin/login');
})->with(godModePanelRoutes());

it('rejects non-super-admin authenticated users with 403', function (string $route): void {
    $hubAdmin = User::factory()->create([
        'role' => Role::HubAdmin,
        'archived_at' => null,
    ]);

    $this->actingAs($hubAdmin)
        ->get($route)
        ->assertForbidden();
})->with(godModePanelRoutes());

it('allows super admin users to load every admin resource route', function (string $route): void {
    $superAdmin = User::factory()->create([
        'role' => Role::SuperAdmin,
        'archived_at' => null,
    ]);

    $this->actingAs($superAdmin)
        ->get($route)
        ->assertOk();
})->with(godModePanelRoutes());
