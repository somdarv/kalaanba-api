<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Kalaanba\Support\Auth\Role;

uses(RefreshDatabase::class);

function seedArea(string $name): string
{
    $countryId = (string) Str::uuid();
    DB::table('countries')->insert([
        'id' => $countryId,
        'code' => strtoupper(substr(uniqid(), -2)),
        'name' => 'C-'.uniqid(),
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    $regionId = (string) Str::uuid();
    DB::table('regions')->insert([
        'id' => $regionId,
        'country_id' => $countryId,
        'code' => 'r-'.substr(uniqid(), -6),
        'name' => 'R-'.uniqid(),
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    $hubId = (string) Str::uuid();
    DB::table('city_hubs')->insert([
        'id' => $hubId,
        'region_id' => $regionId,
        'code' => 'h-'.substr(uniqid(), -6),
        'name' => 'H-'.uniqid(),
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    $zoneId = (string) Str::uuid();
    DB::table('zones')->insert([
        'id' => $zoneId,
        'city_hub_id' => $hubId,
        'kind' => 'zone',
        'code' => 'z-'.substr(uniqid(), -6),
        'name' => 'Z-'.uniqid(),
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    $areaId = (string) Str::uuid();
    DB::table('areas')->insert([
        'id' => $areaId,
        'zone_id' => $zoneId,
        'code' => 'a-'.substr(uniqid(), -6),
        'name' => $name,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    return $areaId;
}

it('GET /users/{id} returns the public projection', function (): void {
    $areaId = seedArea('Aboabo');
    $user = User::factory()->create([
        'name' => 'Kojo Boateng',
        'area_id' => $areaId,
        'avatar_url' => 'https://cdn.example/u.png',
    ]);

    $response = $this->withHeaders(['Accept' => 'application/json'])
        ->getJson('/api/v1/users/'.$user->getKey());

    $response->assertOk()
        ->assertJsonPath('data.id', (string) $user->getKey())
        ->assertJsonPath('data.name', 'Kojo Boateng')
        ->assertJsonPath('data.area_name', 'Aboabo')
        ->assertJsonPath('data.avatar_url', 'https://cdn.example/u.png')
        ->assertJsonPath('data.badges', []);
});

it('GET /users/{id} returns the admin badge for HubAdmin / KalaanbaAdmin / SuperAdmin', function (Role $role, array $expectedBadges): void {
    $user = User::factory()->withRole($role)->create();

    $response = $this->withHeaders(['Accept' => 'application/json'])
        ->getJson('/api/v1/users/'.$user->getKey());

    $response->assertOk()->assertJsonPath('data.badges', $expectedBadges);
})->with([
    'HubAdmin' => [Role::HubAdmin, ['admin']],
    'KalaanbaAdmin' => [Role::KalaanbaAdmin, ['admin']],
    'SuperAdmin' => [Role::SuperAdmin, ['admin']],
    'Referee' => [Role::Referee, ['referee']],
    'FacilityManager' => [Role::FacilityManager, ['facility_manager']],
    'Player' => [Role::Player, []],
]);

it('GET /users/{id} returns 404 for archived users (no leakage)', function (): void {
    $user = User::factory()->archived()->create();

    $this->withHeaders(['Accept' => 'application/json'])
        ->getJson('/api/v1/users/'.$user->getKey())
        ->assertNotFound();
});

it('GET /users/{id} returns 404 for unknown ids', function (): void {
    $this->withHeaders(['Accept' => 'application/json'])
        ->getJson('/api/v1/users/'.(string) Str::uuid())
        ->assertNotFound();
});

it('GET /users/{id} NEVER exposes phone, email, or archive fields', function (): void {
    $user = User::factory()->withPhone('+233244000000')->create();

    $response = $this->withHeaders(['Accept' => 'application/json'])
        ->getJson('/api/v1/users/'.$user->getKey());

    $response->assertOk();
    $body = $response->json('data');
    expect($body)->toHaveKeys(['id', 'name', 'area_name', 'avatar_url', 'badges']);
    expect($body)->not->toHaveKey('phone_e164_hash');
    expect($body)->not->toHaveKey('phone_e164_last4');
    expect($body)->not->toHaveKey('email');
    expect($body)->not->toHaveKey('email_verified_at');
    expect($body)->not->toHaveKey('archived_at');
    expect($body)->not->toHaveKey('role');
    expect($body)->not->toHaveKey('area_id');
});
