<?php

declare(strict_types=1);

use App\Models\User;
use Database\Seeders\AdminConfigSeeder;
use Database\Seeders\ZoneHierarchySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Kalaanba\Modules\Club\Application\CreateClub;
use Kalaanba\Modules\Club\Domain\ClubTier;
use Laravel\Sanctum\Sanctum;

/**
 * POST /api/v1/clubs/{clubId}/crest — the club crest (engine doc §5 step 6, §7).
 *
 * Contract: contracts/api/club/post-clubs-id-crest.v1.yaml.
 */
uses(RefreshDatabase::class);

beforeEach(function (): void {
    Storage::fake('public');
    $this->seed(ZoneHierarchySeeder::class);
    $this->seed(AdminConfigSeeder::class);
    $this->cityHubId = '8c2f9d0a-2c5b-4e3e-9c1e-6a3b1a0e1003';
    $this->areaId = '8c2f9d0a-2c5b-4e3e-9c1e-6a3b1a0e1005';
});

function crestHeaders(): array
{
    return ['Idempotency-Key' => (string) Str::uuid()];
}

function makeClubForCrest(User $owner, string $name = 'Taha Stars'): string
{
    return app(CreateClub::class)->execute(
        $name,
        'community',
        ClubTier::Amateur,
        test()->cityHubId,
        test()->areaId,
        null,
        (string) $owner->getAuthIdentifier(),
    )->id;
}

it('stores a crest for the club owner and returns its address', function (): void {
    Sanctum::actingAs($owner = User::factory()->create(), ['*']);
    $clubId = makeClubForCrest($owner);

    $response = $this->post(
        "/api/v1/clubs/{$clubId}/crest",
        ['file' => UploadedFile::fake()->image('crest.png', 512, 512)],
        crestHeaders(),
    )->assertStatus(201);

    expect($response->json('data.crest_url'))->toBeString()->not->toBeEmpty();

    // The row points at it, so the club renders its crest on the next read.
    expect(DB::table('clubs')->where('id', $clubId)->value('crest_url'))
        ->toBe($response->json('data.crest_url'));
});

it('reports the crest as awaiting moderation, not as cleared', function (): void {
    Sanctum::actingAs($owner = User::factory()->create(), ['*']);
    $clubId = makeClubForCrest($owner);

    // No verdict exists at the instant this response is written. Saying
    // `cleared` would be the Club engine asserting Moderation's truth.
    $this->post(
        "/api/v1/clubs/{$clubId}/crest",
        ['file' => UploadedFile::fake()->image('crest.png')],
        crestHeaders(),
    )
        ->assertStatus(201)
        ->assertJsonPath('data.moderation_status', 'pending');
});

it('tells Moderation through the outbox', function (): void {
    Sanctum::actingAs($owner = User::factory()->create(), ['*']);
    $clubId = makeClubForCrest($owner);

    $this->post(
        "/api/v1/clubs/{$clubId}/crest",
        ['file' => UploadedFile::fake()->image('crest.png')],
        crestHeaders(),
    )->assertStatus(201);

    $event = DB::table('outbox_events')->where('event_name', 'club.crest_updated')->first();

    expect($event)->not->toBeNull();
    expect(json_decode((string) $event->payload, true)['payload']['club_id'])->toBe($clubId);
});

it('refuses someone who does not administer the club', function (): void {
    $owner = User::factory()->create();
    $clubId = makeClubForCrest($owner);

    // A signed-in stranger. §7 puts club identity changes at admin level.
    Sanctum::actingAs(User::factory()->create(), ['*']);

    $this->post(
        "/api/v1/clubs/{$clubId}/crest",
        ['file' => UploadedFile::fake()->image('crest.png')],
        crestHeaders(),
    )
        ->assertStatus(403)
        ->assertJsonPath('error.code', 'club.not_club_admin');
});

it('refuses an unauthenticated upload', function (): void {
    $owner = User::factory()->create();
    $clubId = makeClubForCrest($owner);

    $this->post(
        "/api/v1/clubs/{$clubId}/crest",
        ['file' => UploadedFile::fake()->image('crest.png')],
        crestHeaders(),
    )->assertStatus(401);
});

it('404s on a club that does not exist', function (): void {
    Sanctum::actingAs(User::factory()->create(), ['*']);

    $this->post(
        '/api/v1/clubs/'.Str::uuid().'/crest',
        ['file' => UploadedFile::fake()->image('crest.png')],
        crestHeaders(),
    )
        ->assertStatus(404)
        ->assertJsonPath('error.code', 'club.not_found');
});

it('refuses a file that is not an image', function (): void {
    Sanctum::actingAs($owner = User::factory()->create(), ['*']);
    $clubId = makeClubForCrest($owner);

    // The allow-list is what keeps something executable out of a public bucket.
    $this->post(
        "/api/v1/clubs/{$clubId}/crest",
        ['file' => UploadedFile::fake()->create('payload.php', 8, 'application/x-php')],
        crestHeaders(),
    )->assertStatus(422);
});

it('lets the owner of a club still under review set its crest', function (): void {
    Sanctum::actingAs($owner = User::factory()->create(), ['*']);

    // A professional claim is hidden from everyone else, but its Owner has to
    // be able to finish setting it up while an admin checks it.
    $clubId = app(CreateClub::class)->execute(
        'Asante Kotoko',
        'registered',
        ClubTier::Professional,
        $this->cityHubId,
        $this->areaId,
        null,
        (string) $owner->getAuthIdentifier(),
    )->id;

    $this->post(
        "/api/v1/clubs/{$clubId}/crest",
        ['file' => UploadedFile::fake()->image('crest.png')],
        crestHeaders(),
    )->assertStatus(201);
});

it('stores the same image once, however many times it is sent', function (): void {
    Sanctum::actingAs($owner = User::factory()->create(), ['*']);
    $clubId = makeClubForCrest($owner);

    // Content-addressed: a retry after a dropped connection must not leave a
    // second copy in the bucket.
    $first = $this->post(
        "/api/v1/clubs/{$clubId}/crest",
        ['file' => UploadedFile::fake()->image('crest.png', 300, 300)],
        crestHeaders(),
    )->json('data.crest_url');

    $second = $this->post(
        "/api/v1/clubs/{$clubId}/crest",
        ['file' => UploadedFile::fake()->image('crest.png', 300, 300)],
        crestHeaders(),
    )->json('data.crest_url');

    expect($second)->toBe($first);
});
