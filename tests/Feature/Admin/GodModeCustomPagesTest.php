<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Kalaanba\Support\Auth\Role;

uses(RefreshDatabase::class);

/**
 * @return array<int, string>
 */
function godModeCustomPages(): array
{
    return [
        '/admin/user-inspector',
        '/admin/event-replayer',
        '/admin/data-injector',
    ];
}

it('redirects anonymous visitors on custom God Mode pages', function (string $route): void {
    $this->get($route)->assertRedirect('/admin/login');
})->with(godModeCustomPages());

it('rejects non-super-admin users on custom God Mode pages', function (string $route): void {
    $hubAdmin = User::factory()->create(['role' => Role::HubAdmin, 'archived_at' => null]);
    $this->actingAs($hubAdmin)->get($route)->assertForbidden();
})->with(godModeCustomPages());

it('allows super admin to load every custom God Mode page', function (string $route): void {
    $admin = User::factory()->create(['role' => Role::SuperAdmin, 'archived_at' => null]);
    $this->actingAs($admin)->get($route)->assertOk();
})->with(godModeCustomPages());
