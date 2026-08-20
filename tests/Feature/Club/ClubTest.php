<?php

declare(strict_types=1);

use App\Models\User;
use Database\Seeders\ZoneHierarchySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Kalaanba\Modules\Club\Application\CreateClub;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(ZoneHierarchySeeder::class);
    $this->cityHubId = '8c2f9d0a-2c5b-4e3e-9c1e-6a3b1a0e1003';
    $this->areaId = '8c2f9d0a-2c5b-4e3e-9c1e-6a3b1a0e1005';
});

/**
 * @param  array<string, string>  $extra
 * @return array<string, string>
 */
function clubIdem(array $extra = []): array
{
    return array_merge(['Idempotency-Key' => (string) Str::uuid()], $extra);
}

/**
 * @return array<string, mixed>
 */
function validClubPayload(string $cityHubId, string $areaId, array $overrides = []): array
{
    return array_merge([
        'name' => 'Bantama Boys',
        'club_type' => 'community',
        'city_hub_id' => $cityHubId,
        'area_id' => $areaId,
    ], $overrides);
}

it('rejects unauthenticated club creation with 401', function (): void {
    $this->postJson('/api/v1/clubs', validClubPayload($this->cityHubId, $this->areaId), clubIdem())
        ->assertStatus(401);
});

it('creates a club and makes the creator its Owner', function (): void {
    Sanctum::actingAs($user = User::factory()->create(), ['*']);

    $clubId = $this->postJson('/api/v1/clubs', validClubPayload($this->cityHubId, $this->areaId), clubIdem())
        ->assertStatus(201)
        ->assertJsonPath('data.name', 'Bantama Boys')
        ->assertJsonPath('data.club_type', 'community')
        ->assertJsonPath('data.maturity_level', 'informal')
        ->json('data.id');

    expect(DB::table('club_memberships')->where([
        'club_id' => $clubId,
        'user_id' => (string) $user->getAuthIdentifier(),
        'role' => 'owner',
    ])->exists())->toBeTrue();
});

it('rejects an unknown club type with 422', function (): void {
    Sanctum::actingAs(User::factory()->create(), ['*']);

    $this->postJson('/api/v1/clubs', validClubPayload($this->cityHubId, $this->areaId, ['club_type' => 'spaceship']), clubIdem())
        ->assertStatus(422)
        ->assertJsonValidationErrors(['club_type']);
});

it('rejects an unknown area with 422', function (): void {
    Sanctum::actingAs(User::factory()->create(), ['*']);

    $unknownArea = (string) Str::uuid();
    $this->postJson('/api/v1/clubs', validClubPayload($this->cityHubId, $unknownArea), clubIdem())
        ->assertStatus(422)
        ->assertJsonPath('error.code', 'club.location_unknown');
});

it('lists clubs in an area, newest-first', function (): void {
    Sanctum::actingAs(User::factory()->create(), ['*']);

    $this->postJson('/api/v1/clubs', validClubPayload($this->cityHubId, $this->areaId, ['name' => 'Aboabo United']), clubIdem())
        ->assertStatus(201);

    $this->getJson('/api/v1/clubs?area_id='.$this->areaId)
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.name', 'Aboabo United')
        ->assertJsonPath('meta.count', 1);
});

it('returns an empty list for an area with no clubs', function (): void {
    Sanctum::actingAs(User::factory()->create(), ['*']);

    $this->getJson('/api/v1/clubs?area_id='.Str::uuid())
        ->assertOk()
        ->assertJsonCount(0, 'data');
});

it('requires a valid area_id on the discovery read', function (): void {
    Sanctum::actingAs(User::factory()->create(), ['*']);

    $this->getJson('/api/v1/clubs')
        ->assertStatus(422)
        ->assertJsonPath('error.code', 'club.area_required');
});

it('rejects unauthenticated discovery reads with 401', function (): void {
    $this->getJson('/api/v1/clubs?area_id='.Str::uuid())
        ->assertStatus(401);
});

it('lists only the clubs the caller administers via /clubs/mine', function (): void {
    Sanctum::actingAs($owner = User::factory()->create(), ['*']);
    $this->postJson('/api/v1/clubs', validClubPayload($this->cityHubId, $this->areaId, ['name' => 'My Club']), clubIdem())
        ->assertStatus(201);

    // A club owned by someone else must not appear.
    $other = $this->app->make(CreateClub::class);
    $other->execute('Other Club', 'community', $this->cityHubId, $this->areaId, null, (string) User::factory()->create()->getAuthIdentifier());

    $this->getJson('/api/v1/clubs/mine')
        ->assertOk()
        ->assertJsonPath('meta.count', 1)
        ->assertJsonPath('data.0.name', 'My Club');
});
