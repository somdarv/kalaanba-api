<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

/**
 * GET /api/v1/players/me and PATCH /api/v1/players/{playerId}.
 *
 * Contracts: contracts/api/player/get-players-me.v1.yaml,
 *            contracts/api/player/patch-players-id.v1.yaml.
 */

/**
 * @param  array<string, string>  $extra
 * @return array<string, string>
 */
function myPlayerIdem(array $extra = []): array
{
    return array_merge(['Idempotency-Key' => (string) Str::uuid()], $extra);
}

/**
 * Create a player through the real endpoint so the row under test is one the
 * product could actually produce.
 *
 * @return array{user: User, id: string}
 */
function makePlayer(array $overrides = []): array
{
    $user = User::factory()->create();
    Sanctum::actingAs($user, ['*']);

    $id = test()->postJson('/api/v1/players', array_merge([
        'first_name' => 'Abdul',
        'last_name' => 'Fuseini',
        'stage_name' => 'Baba',
        'preferred_number' => 10,
        'primary_position' => 'striker',
        'availability_status' => 'available',
    ], $overrides), myPlayerIdem())->assertStatus(201)->json('data.id');

    return ['user' => $user, 'id' => (string) $id];
}

// ── GET /players/me ──────────────────────────────────────────────────

it('rejects an unauthenticated read with 401', function (): void {
    $this->getJson('/api/v1/players/me')->assertStatus(401);
});

it('returns 404 with a stable code when the account has no player profile', function (): void {
    // The common case, not a failure: post-signup users are role=user and
    // player-hood is opt-in (engine doc §22). /me renders its no-card half.
    Sanctum::actingAs(User::factory()->create(), ['*']);

    $this->getJson('/api/v1/players/me')
        ->assertStatus(404)
        ->assertJsonPath('error.code', 'player.profile_not_found');
});

it('returns the caller own player record', function (): void {
    makePlayer();

    $this->getJson('/api/v1/players/me')
        ->assertStatus(200)
        ->assertJsonPath('data.stage_name', 'Baba')
        ->assertJsonPath('data.preferred_number', 10)
        ->assertJsonPath('data.primary_position', 'striker')
        ->assertJsonPath('data.market_status', 'free_agent')
        ->assertJsonPath('data.claim_status', 'claimed');
});

it('never leaks another user player through /me', function (): void {
    makePlayer();

    Sanctum::actingAs(User::factory()->create(), ['*']);

    $this->getJson('/api/v1/players/me')->assertStatus(404);
});

it('reports an empty verified record rather than omitting it (§13)', function (): void {
    // Match/Fixture has no module, so nothing anywhere is result_confirmed.
    // Every counter must be present AND zero: a client that has to tell "no
    // stats yet" apart from "field missing" will guess, and a guessed stat is
    // the claimed stat §13 forbids.
    makePlayer();

    $this->getJson('/api/v1/players/me')
        ->assertStatus(200)
        ->assertJsonPath('data.record.appearances', 0)
        ->assertJsonPath('data.record.goals', 0)
        ->assertJsonPath('data.record.assists', 0)
        ->assertJsonPath('data.record.minutes', 0)
        ->assertJsonPath('data.record.yellow_cards', 0)
        ->assertJsonPath('data.record.red_cards', 0);
});

it('starts a new card on the lowest confidence tier (§14)', function (): void {
    makePlayer();

    $this->getJson('/api/v1/players/me')
        ->assertStatus(200)
        ->assertJsonPath('data.confidence.tier', 'provisional')
        ->assertJsonPath('data.confidence.confirmed_matches', 0)
        ->assertJsonPath('data.confidence.next_tier', 'growing')
        ->assertJsonPath('data.confidence.matches_to_next_tier', 3);
});

it('serves no numeric rating anywhere on the record (§14)', function (): void {
    makePlayer();

    $data = $this->getJson('/api/v1/players/me')->assertStatus(200)->json('data');

    expect($data)->not->toHaveKey('rating');
    expect($data['confidence'])->not->toHaveKey('score');
});

// ── PATCH /players/{playerId} ────────────────────────────────────────

it('requires an Idempotency-Key on the write (Law 14)', function (): void {
    ['id' => $id] = makePlayer();

    $this->patchJson("/api/v1/players/{$id}", ['availability_status' => 'limited'])
        ->assertStatus(400);
});

it('updates availability, the one-tap write behind /me', function (): void {
    ['id' => $id] = makePlayer();

    $this->patchJson("/api/v1/players/{$id}", [
        'availability_status' => 'limited',
    ], myPlayerIdem())
        ->assertStatus(200)
        ->assertJsonPath('data.availability_status', 'limited');
});

it('leaves untouched fields alone — an absent key is not a clear', function (): void {
    // Without `sometimes` on every rule this would wipe the profile down to
    // one field, turning a one-tap availability change into a data loss.
    ['id' => $id] = makePlayer();

    $this->patchJson("/api/v1/players/{$id}", [
        'availability_status' => 'limited',
    ], myPlayerIdem())
        ->assertStatus(200)
        ->assertJsonPath('data.stage_name', 'Baba')
        ->assertJsonPath('data.preferred_number', 10)
        ->assertJsonPath('data.primary_position', 'striker');
});

it('clears a nullable field when the caller sends an explicit null', function (): void {
    ['id' => $id] = makePlayer();

    $this->patchJson("/api/v1/players/{$id}", [
        'preferred_number' => null,
    ], myPlayerIdem())
        ->assertStatus(200)
        ->assertJsonPath('data.preferred_number', null);
});

it('refuses to let a caller set backend-derived truth (Law 3)', function (): void {
    ['id' => $id] = makePlayer();

    $this->patchJson("/api/v1/players/{$id}", [
        'market_status' => 'affiliated',
    ], myPlayerIdem())
        ->assertStatus(422);

    // And it stayed put.
    $this->getJson('/api/v1/players/me')
        ->assertJsonPath('data.market_status', 'free_agent');
});

it('rejects an empty patch rather than emitting a no-op event', function (): void {
    ['id' => $id] = makePlayer();

    $this->patchJson("/api/v1/players/{$id}", [], myPlayerIdem())->assertStatus(422);
});

it('rejects a position outside the configured set', function (): void {
    ['id' => $id] = makePlayer();

    $this->patchJson("/api/v1/players/{$id}", [
        'primary_position' => 'sweeper_keeper_libero',
    ], myPlayerIdem())->assertStatus(422);
});

it('rejects a display label where an internal key belongs (Law 4)', function (): void {
    ['id' => $id] = makePlayer();

    $this->patchJson("/api/v1/players/{$id}", [
        'availability_status' => 'Available to play',
    ], myPlayerIdem())->assertStatus(422);
});

it('refuses to let one player edit another (§17)', function (): void {
    ['id' => $victimId] = makePlayer();

    // A second, unrelated account.
    Sanctum::actingAs(User::factory()->create(), ['*']);

    $this->patchJson("/api/v1/players/{$victimId}", [
        'stage_name' => 'Hijacked',
    ], myPlayerIdem())
        ->assertStatus(403)
        ->assertJsonPath('error.code', 'player.not_yours');
});

it('404s on a player that does not exist', function (): void {
    makePlayer();

    $this->patchJson('/api/v1/players/'.Str::uuid(), [
        'stage_name' => 'Ghost',
    ], myPlayerIdem())
        ->assertStatus(404)
        ->assertJsonPath('error.code', 'player.profile_not_found');
});

// ── the cross-engine effect (§12) ────────────────────────────────────

it('emits player.profile_updated through the outbox, flagging the availability move', function (): void {
    ['id' => $id] = makePlayer();

    DB::table('outbox_events')->delete();

    $this->patchJson("/api/v1/players/{$id}", [
        'availability_status' => 'limited',
    ], myPlayerIdem())->assertStatus(200);

    $row = DB::table('outbox_events')->where('event_name', 'player.profile_updated')->first();
    expect($row)->not->toBeNull();

    // The column carries the CANONICAL ENVELOPE (event_id, event_name,
    // schema_version, occurred_at, actor_id, actor_role, source, payload), so
    // the domain payload is the nested key — see Support\EventBus\OutboxEnvelope.
    $envelope = json_decode((string) $row->payload, true);
    expect($envelope['event_name'])->toBe('player.profile_updated');
    expect($envelope['schema_version'])->toBe(1);
    expect($envelope['source'])->toBe('player-affiliation');

    $payload = $envelope['payload'];
    expect($payload['availability_status'])->toBe('limited');
    expect($payload['availability_changed'])->toBeTrue();
    expect($payload['previous_availability_status'])->toBe('available');
});

it('marks availability unchanged when the edit touched something else', function (): void {
    // Club recomputes readiness off this flag (§12). A rename must not look
    // like an availability move.
    ['id' => $id] = makePlayer();

    DB::table('outbox_events')->delete();

    $this->patchJson("/api/v1/players/{$id}", [
        'stage_name' => 'Shakur',
    ], myPlayerIdem())->assertStatus(200);

    $row = DB::table('outbox_events')->where('event_name', 'player.profile_updated')->first();
    $payload = json_decode((string) $row->payload, true)['payload'];

    expect($payload['stage_name'])->toBe('Shakur');
    expect($payload['availability_changed'])->toBeFalse();
    expect($payload['previous_availability_status'])->toBeNull();
});

// ── meta (ADR-0007) ──────────────────────────────────────────────────

it('serves the confidence tier labels on the meta endpoint', function (): void {
    $labels = $this->getJson('/api/v1/players/meta')
        ->assertStatus(200)
        ->json('data.card_confidence');

    expect($labels)->toBeArray();
    expect(collect($labels)->pluck('key')->all())
        ->toBe(['provisional', 'growing', 'verified']);
});
