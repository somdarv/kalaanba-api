<?php

declare(strict_types=1);

use App\Models\User;
use Database\Seeders\ZoneHierarchySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Kalaanba\Modules\Club\Application\CreateClub;
use Kalaanba\Modules\PlayerAffiliation\Application\CreatePlayerProfile;
use Kalaanba\Modules\PlayerAffiliation\Domain\PlayerAvailability;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(ZoneHierarchySeeder::class);
    $this->cityHubId = '8c2f9d0a-2c5b-4e3e-9c1e-6a3b1a0e1003';
    $this->areaId = '8c2f9d0a-2c5b-4e3e-9c1e-6a3b1a0e1005';
    $this->createClub = $this->app->make(CreateClub::class);
    $this->createPlayer = $this->app->make(CreatePlayerProfile::class);
});

/**
 * @param  array<string, string>  $extra
 * @return array<string, string>
 */
function affIdem(array $extra = []): array
{
    return array_merge(['Idempotency-Key' => (string) Str::uuid()], $extra);
}

function makeClubOwnedBy(User $owner): string
{
    return test()->createClub->execute(
        name: 'Bantama Boys',
        clubType: 'community',
        cityHubId: test()->cityHubId,
        areaId: test()->areaId,
        crestUrl: null,
        createdByUserId: (string) $owner->getAuthIdentifier(),
    )->id;
}

function makePlayerFor(User $user): void
{
    test()->createPlayer->execute(
        userId: (string) $user->getAuthIdentifier(),
        firstName: 'Abdul',
        lastName: 'Rahman',
        stageName: 'Kaka',
        preferredNumber: 10,
        primaryPosition: 'forward',
        availability: PlayerAvailability::Available,
        headshotUrl: null,
    );
}

it('lets a player request to join a club (201, requested)', function (): void {
    $owner = User::factory()->create();
    $clubId = makeClubOwnedBy($owner);

    Sanctum::actingAs($player = User::factory()->create(), ['*']);
    makePlayerFor($player);

    $this->postJson("/api/v1/clubs/{$clubId}/join-requests", [], affIdem())
        ->assertStatus(201)
        ->assertJsonPath('data.state', 'requested')
        ->assertJsonPath('data.club_id', $clubId);
});

it('rejects a join request from a user without a player profile (422)', function (): void {
    $owner = User::factory()->create();
    $clubId = makeClubOwnedBy($owner);

    Sanctum::actingAs(User::factory()->create(), ['*']);

    $this->postJson("/api/v1/clubs/{$clubId}/join-requests", [], affIdem())
        ->assertStatus(422)
        ->assertJsonPath('error.code', 'affiliation.request_invalid');
});

it('is idempotent — a second request returns the existing one with 200', function (): void {
    $owner = User::factory()->create();
    $clubId = makeClubOwnedBy($owner);

    Sanctum::actingAs($player = User::factory()->create(), ['*']);
    makePlayerFor($player);

    $first = $this->postJson("/api/v1/clubs/{$clubId}/join-requests", [], affIdem())
        ->assertStatus(201)->json('data.id');

    $this->postJson("/api/v1/clubs/{$clubId}/join-requests", [], affIdem())
        ->assertStatus(200)
        ->assertJsonPath('data.id', $first);
});

it('lets a club owner accept a request, activating the affiliation', function (): void {
    $owner = User::factory()->create();
    $clubId = makeClubOwnedBy($owner);

    Sanctum::actingAs($player = User::factory()->create(), ['*']);
    makePlayerFor($player);
    $affId = $this->postJson("/api/v1/clubs/{$clubId}/join-requests", [], affIdem())->json('data.id');

    Sanctum::actingAs($owner, ['*']);
    $this->postJson("/api/v1/clubs/{$clubId}/join-requests/{$affId}/accept", [], affIdem())
        ->assertStatus(200)
        ->assertJsonPath('data.state', 'active');

    expect(DB::table('affiliations')->where('id', $affId)->value('state'))->toBe('active');
});

it('forbids a non-admin from accepting a request (403)', function (): void {
    $owner = User::factory()->create();
    $clubId = makeClubOwnedBy($owner);

    Sanctum::actingAs($player = User::factory()->create(), ['*']);
    makePlayerFor($player);
    $affId = $this->postJson("/api/v1/clubs/{$clubId}/join-requests", [], affIdem())->json('data.id');

    Sanctum::actingAs(User::factory()->create(), ['*']); // random outsider
    $this->postJson("/api/v1/clubs/{$clubId}/join-requests/{$affId}/accept", [], affIdem())
        ->assertStatus(403)
        ->assertJsonPath('error.code', 'affiliation.not_club_admin');
});

it('lets a club admin list pending requests with the requesting player', function (): void {
    $owner = User::factory()->create();
    $clubId = makeClubOwnedBy($owner);

    Sanctum::actingAs($player = User::factory()->create(), ['*']);
    makePlayerFor($player);
    $this->postJson("/api/v1/clubs/{$clubId}/join-requests", [], affIdem())->assertStatus(201);

    Sanctum::actingAs($owner, ['*']);
    $this->getJson("/api/v1/clubs/{$clubId}/join-requests")
        ->assertOk()
        ->assertJsonPath('meta.count', 1)
        ->assertJsonPath('data.0.state', 'requested')
        ->assertJsonPath('data.0.player.stage_name', 'Kaka');
});

it('forbids a non-admin from listing pending requests (403)', function (): void {
    $owner = User::factory()->create();
    $clubId = makeClubOwnedBy($owner);

    Sanctum::actingAs(User::factory()->create(), ['*']);
    $this->getJson("/api/v1/clubs/{$clubId}/join-requests")
        ->assertStatus(403)
        ->assertJsonPath('error.code', 'affiliation.not_club_admin');
});
