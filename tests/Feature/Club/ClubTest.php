<?php

declare(strict_types=1);

use App\Models\User;
use Database\Seeders\AdminConfigSeeder;
use Database\Seeders\ZoneHierarchySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Kalaanba\Modules\Club\Application\CreateClub;
use Kalaanba\Modules\Club\Domain\ClubTier;
use Kalaanba\Support\Config\Contracts\ConfigRepository;
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
        'tier' => 'amateur',
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
    $other->execute(
        'Other Club',
        'community',
        ClubTier::Amateur,
        $this->cityHubId,
        $this->areaId,
        null,
        (string) User::factory()->create()->getAuthIdentifier(),
    );

    $this->getJson('/api/v1/clubs/mine')
        ->assertOk()
        ->assertJsonPath('meta.count', 1)
        ->assertJsonPath('data.0.name', 'My Club');
});

// ─── Tier + the reserved-name policy (WP-20260823, ADR-0017) ──────────

it('refuses a local club a name that belongs to a real club', function (): void {
    $this->seed(AdminConfigSeeder::class);
    Sanctum::actingAs(User::factory()->create(), ['*']);

    $this->postJson(
        '/api/v1/clubs',
        validClubPayload($this->cityHubId, $this->areaId, ['name' => 'Asante Kotoko']),
        clubIdem(),
    )
        ->assertStatus(422)
        ->assertJsonPath('error.code', 'club.name_reserved');

    // Nothing is written and nothing is announced. A refusal that still emitted
    // club.created would put the name into Analytics and the feed anyway.
    expect(DB::table('clubs')->count())->toBe(0);
    expect(DB::table('outbox_events')->where('event_name', 'club.created')->count())->toBe(0);
});

it('refuses a protected name however it is dressed up', function (string $name): void {
    $this->seed(AdminConfigSeeder::class);
    Sanctum::actingAs(User::factory()->create(), ['*']);

    $this->postJson(
        '/api/v1/clubs',
        validClubPayload($this->cityHubId, $this->areaId, ['name' => $name]),
        clubIdem(),
    )
        ->assertStatus(422)
        ->assertJsonPath('error.code', 'club.name_reserved');
})->with(['Asante Kotoko FC', 'Tamale Manchester United', 'Man Utd', 'asante  kotoko']);

it('does not name the club a refused name collided with', function (): void {
    $this->seed(AdminConfigSeeder::class);
    Sanctum::actingAs(User::factory()->create(), ['*']);

    // Returning "this is reserved for Asante Kotoko" would confirm the list's
    // contents to anyone probing it.
    $body = $this->postJson(
        '/api/v1/clubs',
        validClubPayload($this->cityHubId, $this->areaId, ['name' => 'Asante Kotoko']),
        clubIdem(),
    )->getContent();

    expect($body)->not->toContain('Kotoko');
});

it('lets an ordinary name through that merely shares a word', function (): void {
    $this->seed(AdminConfigSeeder::class);
    Sanctum::actingAs(User::factory()->create(), ['*']);

    $this->postJson(
        '/api/v1/clubs',
        validClubPayload($this->cityHubId, $this->areaId, ['name' => 'Kotoko Boys']),
        clubIdem(),
    )
        ->assertStatus(201)
        ->assertJsonPath('data.name', 'Kotoko Boys');
});

it('lets an official club claim a protected name, and holds it', function (): void {
    $this->seed(AdminConfigSeeder::class);
    Sanctum::actingAs(User::factory()->create(), ['*']);

    $this->postJson(
        '/api/v1/clubs',
        validClubPayload($this->cityHubId, $this->areaId, [
            'name' => 'Asante Kotoko',
            'tier' => 'professional',
            'club_type' => 'registered',
        ]),
        clubIdem(),
    )
        ->assertStatus(201)
        ->assertJsonPath('data.verification_state', 'pending')
        ->assertJsonPath('data.verification_source', 'documents')
        ->assertJsonPath('data.maturity_level', 'registered');
});

it('hides a club under review from discovery', function (): void {
    $this->seed(AdminConfigSeeder::class);
    Sanctum::actingAs(User::factory()->create(), ['*']);

    $this->postJson(
        '/api/v1/clubs',
        validClubPayload($this->cityHubId, $this->areaId, [
            'name' => 'Asante Kotoko',
            'tier' => 'professional',
            'club_type' => 'registered',
        ]),
        clubIdem(),
    )->assertStatus(201);

    // It exists, and nobody looking for a club near them can see it.
    expect(DB::table('clubs')->count())->toBe(1);

    $this->getJson('/api/v1/clubs?area_id='.$this->areaId)
        ->assertOk()
        ->assertJsonCount(0, 'data');
});

it('shows a club under review to its own Owner', function (): void {
    $this->seed(AdminConfigSeeder::class);
    Sanctum::actingAs(User::factory()->create(), ['*']);

    $this->postJson(
        '/api/v1/clubs',
        validClubPayload($this->cityHubId, $this->areaId, [
            'name' => 'Asante Kotoko',
            'tier' => 'professional',
            'club_type' => 'registered',
        ]),
        clubIdem(),
    )->assertStatus(201);

    // Hiding it from the person who created it reads as the club having
    // vanished, and they file a second claim.
    $this->getJson('/api/v1/clubs/mine')
        ->assertOk()
        ->assertJsonPath('meta.count', 1)
        ->assertJsonPath('data.0.verification_state', 'pending');
});

it('marks a local club as needing no verification', function (): void {
    $this->seed(AdminConfigSeeder::class);
    Sanctum::actingAs(User::factory()->create(), ['*']);

    $this->postJson('/api/v1/clubs', validClubPayload($this->cityHubId, $this->areaId), clubIdem())
        ->assertStatus(201)
        ->assertJsonPath('data.verification_state', 'not_required')
        ->assertJsonPath('data.verification_source', null);
});

it('refuses a club type that does not belong to the chosen tier', function (): void {
    $this->seed(AdminConfigSeeder::class);
    Sanctum::actingAs(User::factory()->create(), ['*']);

    // "registered" is an official type. Picking it behind the local door would
    // otherwise be a way to be a registered club with nothing checked.
    $this->postJson(
        '/api/v1/clubs',
        validClubPayload($this->cityHubId, $this->areaId, ['club_type' => 'registered']),
        clubIdem(),
    )
        ->assertStatus(422)
        // Laravel's own 422 shape, NOT the standard error envelope. The API
        // does not wrap validation failures, so the stable key this Form
        // Request declares never reaches a client that could route on it.
        // Out of scope here; recorded as its own finding.
        ->assertJsonValidationErrors(['club_type']);
});

it('refuses an unknown tier', function (): void {
    $this->seed(AdminConfigSeeder::class);
    Sanctum::actingAs(User::factory()->create(), ['*']);

    $this->postJson(
        '/api/v1/clubs',
        validClubPayload($this->cityHubId, $this->areaId, ['tier' => 'semi_pro']),
        clubIdem(),
    )
        ->assertStatus(422)
        ->assertJsonValidationErrors(['tier']);
});

it('starts refusing a name the moment it is added to config', function (): void {
    $this->seed(AdminConfigSeeder::class);
    Sanctum::actingAs(User::factory()->create(), ['*']);

    // Not on the list yet.
    $this->postJson(
        '/api/v1/clubs',
        validClubPayload($this->cityHubId, $this->areaId, ['name' => 'Tamale Warriors']),
        clubIdem(),
    )->assertStatus(201);

    app(ConfigRepository::class)->set(
        'club.name.reserved_terms',
        json_encode([['canonical' => 'Tamale Warriors', 'aliases' => []]], JSON_THROW_ON_ERROR),
        approvalLevel: 'medium',
    );

    // No deploy, no restart. That is what makes the list governable.
    $this->postJson(
        '/api/v1/clubs',
        validClubPayload($this->cityHubId, $this->areaId, ['name' => 'Tamale Warriors FC']),
        clubIdem(),
    )
        ->assertStatus(422)
        ->assertJsonPath('error.code', 'club.name_reserved');
});

it('carries the tier and the verification state on club.created', function (): void {
    $this->seed(AdminConfigSeeder::class);
    Sanctum::actingAs(User::factory()->create(), ['*']);

    $this->postJson(
        '/api/v1/clubs',
        validClubPayload($this->cityHubId, $this->areaId, [
            'name' => 'Real Tamale Academy',
            'tier' => 'professional',
            'club_type' => 'academy',
        ]),
        clubIdem(),
    )->assertStatus(201);

    $event = DB::table('outbox_events')->where('event_name', 'club.created')->first();

    expect($event)->not->toBeNull();

    // The column stores the whole envelope; the domain payload nests inside it.
    $payload = json_decode((string) $event->payload, true)['payload'];

    // A consumer that puts a club in front of the public must be able to read
    // this off the event without going back to the database.
    expect($payload['tier'])->toBe('professional')
        ->and($payload['verification_state'])->toBe('pending');
});
