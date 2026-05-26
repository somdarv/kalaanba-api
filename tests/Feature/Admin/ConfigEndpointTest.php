<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Kalaanba\Support\Auth\Role;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    DB::table('admin_config')->updateOrInsert(
        ['key' => 'zone.test_marker', 'scope' => 'platform', 'scope_id' => null],
        [
            'value' => 'true',
            'effective_from' => '2026-01-01 00:00:00+00',
            'version' => 1,
            'approval_level' => 'medium',
            'change_reason' => 'config-endpoint test seed',
            'created_at' => '2026-01-01 00:00:00+00',
            'updated_at' => '2026-01-01 00:00:00+00',
        ],
    );
});

it('rejects unauthenticated callers with 401', function (): void {
    $this->getJson('/api/v1/admin/configs')->assertStatus(401);
});

it('rejects authenticated non-super-admins with auth.super_admin_only', function (): void {
    $hub = User::factory()->withRole(Role::HubAdmin)->create();
    Sanctum::actingAs($hub, ['*']);

    $this->getJson('/api/v1/admin/configs')
        ->assertStatus(403)
        ->assertJsonPath('error.code', 'auth.super_admin_only');
});

it('lists admin_config rows for a Super Admin', function (): void {
    $super = User::factory()->withRole(Role::SuperAdmin)->create();
    Sanctum::actingAs($super, ['*']);

    $response = $this->getJson('/api/v1/admin/configs?limit=200');
    $response->assertOk();

    $keys = collect($response->json('data'))->pluck('key')->all();
    expect($keys)->toContain('zone.test_marker');
    expect($response->json('meta.limit'))->toBe(200);
});

it('filters by engine prefix', function (): void {
    $super = User::factory()->withRole(Role::SuperAdmin)->create();
    Sanctum::actingAs($super, ['*']);

    $response = $this->getJson('/api/v1/admin/configs?engine=zone');
    $response->assertOk();

    $keys = collect($response->json('data'))->pluck('key')->all();
    expect($keys)->toContain('zone.test_marker');
    foreach ($keys as $key) {
        expect(str_starts_with((string) $key, 'zone.'))->toBeTrue();
    }
});
