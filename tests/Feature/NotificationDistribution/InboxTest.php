<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Kalaanba\Modules\NotificationDistribution\Application\CreateInboxItemService;
use Kalaanba\Modules\NotificationDistribution\Domain\InboxCategory;
use Kalaanba\Modules\NotificationDistribution\Domain\InboxUrgency;
use Kalaanba\Modules\NotificationDistribution\Domain\NewInboxItem;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

function jsonHeaders(?string $idempotencyKey = null): array
{
    return [
        'Accept' => 'application/json',
        'Idempotency-Key' => $idempotencyKey ?? (string) Str::uuid(),
    ];
}

function createInboxItemFor(User $user, array $overrides = []): string
{
    /** @var CreateInboxItemService $service */
    $service = app(CreateInboxItemService::class);

    return $service->handle(new NewInboxItem(
        recipientUserId: (string) $user->getKey(),
        templateKey: $overrides['template_key'] ?? 'match.scheduled',
        category: $overrides['category'] ?? InboxCategory::Match,
        urgency: $overrides['urgency'] ?? InboxUrgency::Normal,
        title: $overrides['title'] ?? 'Match scheduled',
        body: $overrides['body'] ?? 'Your match is on Sunday.',
        sourceType: $overrides['source_type'] ?? 'match',
        sourceId: $overrides['source_id'] ?? null,
        actionUrl: $overrides['action_url'] ?? null,
        payload: $overrides['payload'] ?? [],
    ));
}

it('returns an empty inbox for a new user', function (): void {
    $user = User::factory()->create();
    Sanctum::actingAs($user);

    $response = $this->withHeaders(['Accept' => 'application/json'])
        ->getJson('/api/v1/me/notifications');

    $response->assertOk()
        ->assertJsonPath('data', [])
        ->assertJsonPath('meta.next_cursor', null);
});

it('lists only the caller\'s own notifications', function (): void {
    $alice = User::factory()->create();
    $bob = User::factory()->create();

    createInboxItemFor($alice, ['title' => 'For Alice']);
    createInboxItemFor($bob, ['title' => 'For Bob']);

    Sanctum::actingAs($alice);
    $response = $this->withHeaders(['Accept' => 'application/json'])
        ->getJson('/api/v1/me/notifications');

    $response->assertOk();
    $titles = collect($response->json('data'))->pluck('title')->all();
    expect($titles)->toBe(['For Alice']);
});

it('paginates with a cursor', function (): void {
    $user = User::factory()->create();
    for ($i = 0; $i < 5; $i++) {
        createInboxItemFor($user, ['title' => "Item {$i}"]);
        // Force distinct created_at ordering even on coarse clocks.
        usleep(2_000);
    }
    Sanctum::actingAs($user);

    $first = $this->withHeaders(['Accept' => 'application/json'])
        ->getJson('/api/v1/me/notifications?limit=2');
    $first->assertOk()
        ->assertJsonCount(2, 'data');
    $cursor = $first->json('meta.next_cursor');
    expect($cursor)->not->toBeNull();

    $second = $this->withHeaders(['Accept' => 'application/json'])
        ->getJson('/api/v1/me/notifications?limit=2&cursor='.urlencode($cursor));
    $second->assertOk()
        ->assertJsonCount(2, 'data');

    $firstIds = collect($first->json('data'))->pluck('id')->all();
    $secondIds = collect($second->json('data'))->pluck('id')->all();
    expect(array_intersect($firstIds, $secondIds))->toBe([]);
});

it('counts unread items and reports truncation', function (): void {
    $user = User::factory()->create();
    createInboxItemFor($user);
    createInboxItemFor($user);
    createInboxItemFor($user);
    Sanctum::actingAs($user);

    $response = $this->withHeaders(['Accept' => 'application/json'])
        ->getJson('/api/v1/me/notifications/unread-count');

    $response->assertOk()
        ->assertJsonPath('data.count', 3)
        ->assertJsonPath('data.truncated', false);
});

it('marks an item as seen idempotently', function (): void {
    $user = User::factory()->create();
    $id = createInboxItemFor($user);
    Sanctum::actingAs($user);

    $first = $this->withHeaders(jsonHeaders())
        ->postJson("/api/v1/me/notifications/{$id}/seen");
    $first->assertNoContent();

    // Second call (new idempotency key) is still a successful no-op.
    $second = $this->withHeaders(jsonHeaders())
        ->postJson("/api/v1/me/notifications/{$id}/seen");
    $second->assertNoContent();

    $unread = $this->withHeaders(['Accept' => 'application/json'])
        ->getJson('/api/v1/me/notifications/unread-count');
    $unread->assertJsonPath('data.count', 0);
});

it('marks an item as acted_on after seen', function (): void {
    $user = User::factory()->create();
    $id = createInboxItemFor($user);
    Sanctum::actingAs($user);

    $this->withHeaders(jsonHeaders())
        ->postJson("/api/v1/me/notifications/{$id}/seen")
        ->assertNoContent();

    $this->withHeaders(jsonHeaders())
        ->postJson("/api/v1/me/notifications/{$id}/acted-on")
        ->assertNoContent();
});

it('returns 404 when marking a foreign user\'s item', function (): void {
    $alice = User::factory()->create();
    $bob = User::factory()->create();
    $id = createInboxItemFor($bob);

    Sanctum::actingAs($alice);
    $response = $this->withHeaders(jsonHeaders())
        ->postJson("/api/v1/me/notifications/{$id}/seen");

    $response->assertNotFound();
});

it('requires Idempotency-Key on mark-seen', function (): void {
    $user = User::factory()->create();
    $id = createInboxItemFor($user);
    Sanctum::actingAs($user);

    $response = $this->withHeaders(['Accept' => 'application/json'])
        ->postJson("/api/v1/me/notifications/{$id}/seen");

    $response->assertStatus(400);
});

it('rejects unauthenticated requests', function (): void {
    $this->withHeaders(['Accept' => 'application/json'])
        ->getJson('/api/v1/me/notifications')
        ->assertStatus(401);
});

it('filters by category', function (): void {
    $user = User::factory()->create();
    createInboxItemFor($user, ['category' => InboxCategory::Match, 'title' => 'M']);
    createInboxItemFor($user, ['category' => InboxCategory::Trust, 'title' => 'T']);
    Sanctum::actingAs($user);

    $response = $this->withHeaders(['Accept' => 'application/json'])
        ->getJson('/api/v1/me/notifications?category=trust');

    $response->assertOk()->assertJsonCount(1, 'data');
    expect($response->json('data.0.title'))->toBe('T');
});
