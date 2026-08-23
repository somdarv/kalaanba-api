<?php

declare(strict_types=1);

use Database\Seeders\AdminConfigSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Kalaanba\Support\Config\Contracts\ConfigRepository;

/**
 * GET /api/v1/clubs/meta — the club-creation vocabulary (ADR-0007).
 *
 * Contract: contracts/api/club/get-clubs-meta.v1.yaml.
 */
uses(RefreshDatabase::class);

it('serves the vocabulary without a token', function (): void {
    // Public reference data: no club, no user, nothing computed. A signed-out
    // marketing surface needs it too.
    $this->getJson('/api/v1/clubs/meta')->assertOk();
});

it('returns the two tiers in configured order with their labels', function (): void {
    $this->seed(AdminConfigSeeder::class);

    $this->getJson('/api/v1/clubs/meta')
        ->assertOk()
        ->assertJsonPath('data.tiers.0.key', 'amateur')
        ->assertJsonPath('data.tiers.0.label', 'Amateur')
        ->assertJsonPath('data.tiers.1.key', 'professional')
        ->assertJsonPath('data.tiers.1.label', 'Professional');
});

it('tells the client which tier each club type belongs to', function (): void {
    $this->seed(AdminConfigSeeder::class);

    $types = collect($this->getJson('/api/v1/clubs/meta')->json('data.types'))
        ->keyBy('key');

    expect($types['community']['tier'])->toBe('amateur')
        ->and($types['school']['tier'])->toBe('amateur')
        ->and($types['academy']['tier'])->toBe('professional')
        ->and($types['registered']['tier'])->toBe('professional');
});

it('falls back to the key when a label is missing', function (): void {
    $this->seed(AdminConfigSeeder::class);

    // A half-written label map must never render a blank, unselectable option.
    app(ConfigRepository::class)->set(
        'club.types.labels',
        json_encode(['community' => 'Community club'], JSON_THROW_ON_ERROR),
        approvalLevel: 'low',
    );

    $types = collect($this->getJson('/api/v1/clubs/meta')->json('data.types'))
        ->keyBy('key');

    expect($types['community']['label'])->toBe('Community club')
        ->and($types['academy']['label'])->toBe('academy');
});

it('serves the name bounds so the client derives its own validation', function (): void {
    $this->seed(AdminConfigSeeder::class);

    $this->getJson('/api/v1/clubs/meta')
        ->assertOk()
        ->assertJsonPath('data.name.min_length', 2)
        ->assertJsonPath('data.name.max_length', 120);
});

it('never serves the reserved-name list', function (): void {
    $this->seed(AdminConfigSeeder::class);

    $body = $this->getJson('/api/v1/clubs/meta')->getContent();

    // Publishing the list publishes the map for routing around it, and the
    // verdict is backend truth either way (ADR-0017 §4).
    expect($body)->not->toContain('reserved')
        ->and($body)->not->toContain('Kotoko');
});

it('revalidates with an ETag and answers 304', function (): void {
    $this->seed(AdminConfigSeeder::class);

    $etag = $this->getJson('/api/v1/clubs/meta')->headers->get('ETag');

    expect($etag)->not->toBeNull();

    $this->withHeaders(['If-None-Match' => (string) $etag])
        ->getJson('/api/v1/clubs/meta')
        ->assertStatus(304);
});

it('varies on Accept-Language', function (): void {
    $this->seed(AdminConfigSeeder::class);

    expect($this->getJson('/api/v1/clubs/meta')->headers->get('Vary'))
        ->toBe('Accept-Language');
});

it('changes its ETag when a sourced config key changes', function (): void {
    $this->seed(AdminConfigSeeder::class);

    $before = $this->getJson('/api/v1/clubs/meta')->headers->get('ETag');

    app(ConfigRepository::class)->set(
        'club.types.labels',
        json_encode(['community' => 'Neighbourhood side'], JSON_THROW_ON_ERROR),
        approvalLevel: 'low',
    );

    expect($this->getJson('/api/v1/clubs/meta')->headers->get('ETag'))
        ->not->toBe($before);
});

it('offers a club type added in config, with no deploy', function (): void {
    $this->seed(AdminConfigSeeder::class);

    // The config-flip proof. This is the whole point of ADR-0007: an admin adds
    // a type and the creation form offers it, without anyone touching the client.
    $config = app(ConfigRepository::class);
    $config->set(
        'club.types',
        json_encode(['community', 'ladies'], JSON_THROW_ON_ERROR),
        approvalLevel: 'medium',
    );
    $config->set(
        'club.types.labels',
        json_encode(['community' => 'Community club', 'ladies' => 'Ladies team'], JSON_THROW_ON_ERROR),
        approvalLevel: 'low',
    );
    $config->set(
        'club.types.tier',
        json_encode(['community' => 'amateur', 'ladies' => 'amateur'], JSON_THROW_ON_ERROR),
        approvalLevel: 'medium',
    );

    $types = collect($this->getJson('/api/v1/clubs/meta')->json('data.types'))
        ->keyBy('key');

    expect($types)->toHaveKey('ladies')
        ->and($types['ladies']['label'])->toBe('Ladies team')
        ->and($types['ladies']['tier'])->toBe('amateur');
});
