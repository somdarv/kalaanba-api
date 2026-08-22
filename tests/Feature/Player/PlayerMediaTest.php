<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

/**
 * POST /api/v1/players/{playerId}/media.
 *
 * Contract: contracts/api/player/post-players-id-media.v1.yaml.
 * Engine doc: docs/engines/player-affiliation/ §7, §17.
 *
 * Runs against the `local` driver throughout. The R2 driver differs only in
 * where bytes land and how the URL is spelled; every rule worth testing here
 * (ownership, the allow-list, the ceiling, the headshot write, the moderation
 * event) is driver-independent, and a test that needed a bucket credential
 * would not run in CI.
 */

/**
 * @param  array<string, string>  $extra
 * @return array<string, string>
 */
function mediaIdem(array $extra = []): array
{
    return array_merge(['Idempotency-Key' => (string) Str::uuid()], $extra);
}

/**
 * @return array{user: User, id: string}
 */
function makeMediaPlayer(): array
{
    $user = User::factory()->create();
    Sanctum::actingAs($user, ['*']);

    $id = test()->postJson('/api/v1/players', [
        'first_name' => 'Abdul',
        'last_name' => 'Fuseini',
        'stage_name' => 'Baba',
        'preferred_number' => 10,
        'primary_position' => 'striker',
        'availability_status' => 'available',
    ], mediaIdem())->assertStatus(201)->json('data.id');

    return ['user' => $user, 'id' => (string) $id];
}

beforeEach(function (): void {
    Storage::fake('public');
    config()->set('player.media.driver', 'local');
    config()->set('player.media.local.disk', 'public');
});

it('rejects an unauthenticated upload with 401', function (): void {
    $this->postJson('/api/v1/players/'.Str::uuid().'/media', [
        'file' => UploadedFile::fake()->image('face.jpg'),
        'kind' => 'headshot',
    ], mediaIdem())->assertStatus(401);
});

it('stores a headshot and points the player row at it', function (): void {
    ['id' => $id] = makeMediaPlayer();

    $response = $this->post('/api/v1/players/'.$id.'/media', [
        'file' => UploadedFile::fake()->image('face.jpg', 600, 600),
        'kind' => 'headshot',
    ], mediaIdem());

    $response->assertStatus(201)
        ->assertJsonPath('data.kind', 'headshot')
        ->assertJsonPath('meta.api_version', 'v1');

    $url = (string) $response->json('data.url');
    expect($url)->not->toBe('');

    // The endpoint writes headshot_url itself rather than making the client
    // PATCH afterwards, so a dropped connection cannot leave a stored photo
    // that no card points at.
    $this->getJson('/api/v1/players/me')
        ->assertStatus(200)
        ->assertJsonPath('data.headshot_url', $url);
});

it('reports the moderation verdict as pending, never as cleared', function (): void {
    ['id' => $id] = makeMediaPlayer();

    // Moderation and Safety consumes `player.media_uploaded` asynchronously
    // (Law 6), so no verdict exists at the instant this response is written.
    // Claiming one would be this engine asserting another engine's truth.
    $this->post('/api/v1/players/'.$id.'/media', [
        'file' => UploadedFile::fake()->image('face.jpg'),
        'kind' => 'headshot',
    ], mediaIdem())
        ->assertStatus(201)
        ->assertJsonPath('data.moderation_status', 'pending');
});

it('raises player.media_uploaded through the outbox', function (): void {
    ['id' => $id] = makeMediaPlayer();

    $this->post('/api/v1/players/'.$id.'/media', [
        'file' => UploadedFile::fake()->image('face.jpg'),
        'kind' => 'headshot',
    ], mediaIdem())->assertStatus(201);

    // Public content the moment it lands on a card (Law 10), so Moderation has
    // to be told. Through the outbox, never published directly (Law 6).
    $event = DB::table('outbox_events')
        ->where('event_name', 'player.media_uploaded')
        ->latest('occurred_at')
        ->first();

    expect($event)->not->toBeNull();

    // The column holds the whole canonical envelope; the domain payload is
    // nested inside it under `payload` (contracts/events/README.md).
    $envelope = json_decode((string) $event->payload, true);
    expect($envelope['source'])->toBe('player-affiliation');
    expect($envelope['schema_version'])->toBe(1);
    expect($envelope['payload']['player_id'])->toBe($id);
    expect($envelope['payload']['kind'])->toBe('headshot');
});

it('refuses to let one player set the photo of another', function (): void {
    ['id' => $id] = makeMediaPlayer();

    // Engine doc §17: nobody edits another player record through this route,
    // not even a club admin. Club-side changes go through affiliation (§11).
    Sanctum::actingAs(User::factory()->create(), ['*']);

    $this->post('/api/v1/players/'.$id.'/media', [
        'file' => UploadedFile::fake()->image('face.jpg'),
        'kind' => 'headshot',
    ], mediaIdem())
        ->assertStatus(403)
        ->assertJsonPath('error.code', 'player.not_yours');
});

it('refuses a file type outside the configured allow-list', function (): void {
    ['id' => $id] = makeMediaPlayer();

    $this->post('/api/v1/players/'.$id.'/media', [
        'file' => UploadedFile::fake()->create('cv.pdf', 10, 'application/pdf'),
        'kind' => 'headshot',
    ], mediaIdem())->assertStatus(422);
});

it('refuses a file over the configured ceiling', function (): void {
    ['id' => $id] = makeMediaPlayer();

    // Config-flip: the limit is admin config, not a literal, so moving the key
    // must move the behaviour with no recompile (Law 2).
    config()->set('player.media.max_bytes', 50 * 1024);

    $this->post('/api/v1/players/'.$id.'/media', [
        'file' => UploadedFile::fake()->image('huge.jpg')->size(400),
        'kind' => 'headshot',
    ], mediaIdem())->assertStatus(422);
});

it('refuses an unknown media kind', function (): void {
    ['id' => $id] = makeMediaPlayer();

    $this->post('/api/v1/players/'.$id.'/media', [
        'file' => UploadedFile::fake()->image('face.jpg'),
        'kind' => 'action_shot',
    ], mediaIdem())->assertStatus(422);
});

it('accepts a half-body image without touching headshot_url', function (): void {
    ['id' => $id] = makeMediaPlayer();

    // §7 keeps the three kinds apart. A waist-up shot written into the headshot
    // field would land in every team sheet in the country.
    $this->post('/api/v1/players/'.$id.'/media', [
        'file' => UploadedFile::fake()->image('portrait.jpg', 800, 1000),
        'kind' => 'half_body',
    ], mediaIdem())
        ->assertStatus(201)
        ->assertJsonPath('data.kind', 'half_body');

    $this->getJson('/api/v1/players/me')
        ->assertStatus(200)
        ->assertJsonPath('data.headshot_url', null);
});

it('requires an Idempotency-Key like every user-triggered write', function (): void {
    ['id' => $id] = makeMediaPlayer();

    // Constitution Law 14. Mobile networks retry.
    $this->post('/api/v1/players/'.$id.'/media', [
        'file' => UploadedFile::fake()->image('face.jpg'),
        'kind' => 'headshot',
    ])->assertStatus(400);
});
