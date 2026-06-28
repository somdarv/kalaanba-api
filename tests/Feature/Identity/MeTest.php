<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

function seedTestArea(?string $name = null): string
{
    $id = (string) Str::uuid();
    DB::table('areas')->insert([
        'id' => $id,
        'zone_id' => seedTestZone(),
        'code' => 'test-'.substr($id, 0, 8),
        'name' => $name ?? 'Test Area',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    return $id;
}

function seedTestZone(): string
{
    $existing = DB::table('zones')->where('code', 'identity-test-zone')->value('id');
    if ($existing !== null) {
        return (string) $existing;
    }

    $countryId = (string) Str::uuid();
    DB::table('countries')->insert([
        'id' => $countryId,
        'code' => strtoupper(substr(uniqid(), -2)),
        'name' => 'Test Country '.uniqid(),
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    $regionId = (string) Str::uuid();
    DB::table('regions')->insert([
        'id' => $regionId,
        'country_id' => $countryId,
        'code' => 'tr-'.substr(uniqid(), -6),
        'name' => 'Test Region',
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    $hubId = (string) Str::uuid();
    DB::table('city_hubs')->insert([
        'id' => $hubId,
        'region_id' => $regionId,
        'code' => 'th-'.substr(uniqid(), -6),
        'name' => 'Test Hub',
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    $zoneId = (string) Str::uuid();
    DB::table('zones')->insert([
        'id' => $zoneId,
        'city_hub_id' => $hubId,
        'kind' => 'zone',
        'code' => 'identity-test-zone',
        'name' => 'Identity Test Zone',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    return $zoneId;
}

it('GET /users/me returns the self projection for the authenticated caller', function (): void {
    $user = User::factory()->withPhone('+233244111222')->create([
        'name' => 'Ama Boateng',
    ]);
    Sanctum::actingAs($user);

    $response = $this->withHeaders(['Accept' => 'application/json'])
        ->getJson('/api/v1/users/me');

    $response->assertOk()
        ->assertJsonPath('data.id', (string) $user->getKey())
        ->assertJsonPath('data.name', 'Ama Boateng')
        ->assertJsonPath('data.role', 'fan')
        ->assertJsonPath('data.phone_e164_last4', '1222')
        ->assertJsonStructure([
            'data' => [
                'id', 'name', 'role', 'area_id', 'avatar_url',
                'email', 'email_verified_at', 'phone_e164_last4',
                'archived_at', 'last_seen_at',
            ],
        ]);
});

it('GET /users/me works for a phone-only user with no email', function (): void {
    // Regression: a phone-signup user has email=null; the profile snapshot must
    // allow a null email (Identity §2/§8) instead of 500ing.
    $user = User::factory()->withPhone('+233592123054')->create([
        'name' => 'Yaw Phone',
        'email' => null,
        'password' => null,
        'email_verified_at' => null,
    ]);
    Sanctum::actingAs($user);

    $this->withHeaders(['Accept' => 'application/json'])
        ->getJson('/api/v1/users/me')
        ->assertOk()
        ->assertJsonPath('data.email', null)
        ->assertJsonPath('data.phone_e164_last4', '3054');
});

it('GET /users/me returns 401 without auth', function (): void {
    $this->withHeaders(['Accept' => 'application/json'])
        ->getJson('/api/v1/users/me')
        ->assertUnauthorized();
});

it('PATCH /users/me updates the name', function (): void {
    $user = User::factory()->create(['name' => 'Old Name']);
    Sanctum::actingAs($user);

    $response = $this->withHeaders([
        'Accept' => 'application/json',
        'Idempotency-Key' => (string) Str::uuid(),
    ])->patchJson('/api/v1/users/me', ['name' => 'New Name']);

    $response->assertOk()->assertJsonPath('data.name', 'New Name');
    expect($user->fresh()->name)->toBe('New Name');
});

it('PATCH /users/me rejects name shorter than the config minimum', function (): void {
    $user = User::factory()->create();
    Sanctum::actingAs($user);

    $response = $this->withHeaders([
        'Accept' => 'application/json',
        'Idempotency-Key' => (string) Str::uuid(),
    ])->patchJson('/api/v1/users/me', ['name' => 'a']);

    $response->assertStatus(422)
        ->assertJsonPath('errors.name.0', 'identity.profile.name_invalid');
});

it('PATCH /users/me sets a known area_id', function (): void {
    $user = User::factory()->create();
    Sanctum::actingAs($user);
    $areaId = seedTestArea('Aboabo');

    $response = $this->withHeaders([
        'Accept' => 'application/json',
        'Idempotency-Key' => (string) Str::uuid(),
    ])->patchJson('/api/v1/users/me', ['area_id' => $areaId]);

    $response->assertOk()->assertJsonPath('data.area_id', $areaId);
});

it('PATCH /users/me returns 422 identity.profile.area_unknown for an unknown area_id', function (): void {
    $user = User::factory()->create();
    Sanctum::actingAs($user);

    $response = $this->withHeaders([
        'Accept' => 'application/json',
        'Idempotency-Key' => (string) Str::uuid(),
    ])->patchJson('/api/v1/users/me', ['area_id' => (string) Str::uuid()]);

    $response->assertStatus(422)
        ->assertJsonPath('errors.area_id.0', 'identity.profile.area_unknown');
});

it('PATCH /users/me silently drops disallowed fields like role and email', function (): void {
    $user = User::factory()->create(['name' => 'Original']);
    Sanctum::actingAs($user);

    $response = $this->withHeaders([
        'Accept' => 'application/json',
        'Idempotency-Key' => (string) Str::uuid(),
    ])->patchJson('/api/v1/users/me', [
        'name' => 'Renamed',
        'role' => 'super_admin',
        'email' => 'hacker@example.com',
    ]);

    $response->assertOk();
    $fresh = $user->fresh();
    expect($fresh->role->value)->toBe('fan');
    expect($fresh->email)->not->toBe('hacker@example.com');
});
