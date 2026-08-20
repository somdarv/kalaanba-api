<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Kalaanba\Support\Config as KxConfig;

uses(RefreshDatabase::class);

/**
 * GET /api/v1/players/meta — the profile-form vocabulary (ADR-0007).
 *
 * The point of the endpoint is that an admin can change an option set, a
 * label, or a bound and have it reach the form without a deploy. Most of these
 * tests therefore write config first and assert the response moved with it —
 * asserting the seeded defaults alone would pass just as well against a
 * hardcoded list, which is the bug this replaced.
 */
it('is public reference data — no auth required', function (): void {
    $this->getJson('/api/v1/players/meta')
        ->assertStatus(200)
        ->assertJsonStructure([
            'data' => [
                'positions' => [['key', 'label']],
                'availability' => [['key', 'label']],
                'availability_default',
                'market_statuses' => [['key', 'label']],
                'preferred_number' => ['min', 'max', 'quick_picks'],
                'name' => ['max_length', 'stage_name_max_length'],
            ],
            'meta',
        ]);
});

it('serves the configured positions, in configured order', function (): void {
    KxConfig::set(
        'player.positions',
        json_encode(['goalkeeper', 'defender', 'midfielder', 'winger', 'forward']),
        changeReason: 'test',
    );
    KxConfig::set(
        'player.positions.labels',
        json_encode([
            'goalkeeper' => 'Keeper',
            'defender' => 'Defender',
            'midfielder' => 'Midfielder',
            'winger' => 'Winger',
            'forward' => 'Striker',
        ]),
        changeReason: 'test',
    );

    $response = $this->getJson('/api/v1/players/meta')->assertStatus(200);

    expect(array_column($response->json('data.positions'), 'key'))
        ->toBe(['goalkeeper', 'defender', 'midfielder', 'winger', 'forward']);
    expect(array_column($response->json('data.positions'), 'label'))
        ->toBe(['Keeper', 'Defender', 'Midfielder', 'Winger', 'Striker']);
});

it('falls back to the internal key when a label is missing', function (): void {
    KxConfig::set('player.positions', json_encode(['goalkeeper', 'sweeper']), changeReason: 'test');
    KxConfig::set(
        'player.positions.labels',
        json_encode(['goalkeeper' => 'Keeper']),
        changeReason: 'test',
    );

    $positions = $this->getJson('/api/v1/players/meta')->json('data.positions');

    // A missing label must never render an unselectable blank option. The
    // short form falls back the same way, because a marker with no text on it
    // is just as unusable as an option with no name.
    expect($positions[1])->toBe([
        'key' => 'sweeper',
        'label' => 'sweeper',
        'abbreviation' => 'sweeper',
        'description' => null,
    ]);
});

it('relabels availability without touching its keys', function (): void {
    KxConfig::set(
        'player.availability.labels',
        json_encode([
            'available' => 'Ready to Go',
            'limited' => 'Available with notice',
            'unavailable' => 'Not available',
            'unknown' => 'Not sure yet',
        ]),
        changeReason: 'engine doc §12 anticipates exactly this rename',
    );

    $availability = $this->getJson('/api/v1/players/meta')->json('data.availability');

    expect(array_column($availability, 'key'))
        ->toBe(['available', 'limited', 'unavailable', 'unknown']);
    expect($availability[0]['label'])->toBe('Ready to Go');
});

it('carries the consequence copy for each availability option', function (): void {
    $descriptions = array_column(
        $this->getJson('/api/v1/players/meta')->json('data.availability'),
        'description',
        'key',
    );

    expect($descriptions['available'])->toBeString()->not->toBeEmpty();
});

it('serves the authoritative shirt-number range', function (): void {
    KxConfig::set('player.profile.preferred_number_min', '1', changeReason: 'test');
    KxConfig::set('player.profile.preferred_number_max', '50', changeReason: 'test');

    $bounds = $this->getJson('/api/v1/players/meta')->json('data.preferred_number');

    expect($bounds['min'])->toBe(1);
    expect($bounds['max'])->toBe(50);
});

it('drops quick picks that fall outside the configured range', function (): void {
    KxConfig::set('player.profile.preferred_number_max', '10', changeReason: 'test');
    KxConfig::set(
        'player.profile.preferred_number_quick_picks',
        json_encode([1, 7, 10, 11, 99]),
        changeReason: 'test',
    );

    $picks = $this->getJson('/api/v1/players/meta')->json('data.preferred_number.quick_picks');

    // 11 and 99 are unreachable under this range — serving them would offer a
    // choice the create endpoint then rejects.
    expect($picks)->toBe([1, 7, 10]);
});

it('survives a config store with nothing in it', function (): void {
    DB::table('admin_config')->delete();

    $data = $this->getJson('/api/v1/players/meta')->assertStatus(200)->json('data');

    // The compiled fallback, which must stay identical to the default in
    // contracts/config/player/player.positions.yaml. If these drift, an
    // environment with no config row answers a different question than the
    // contract promises, which is exactly how every environment ended up
    // serving four positions while the contract said thirteen.
    expect(array_column($data['positions'], 'key'))->toBe([
        'goalkeeper',
        'left_back', 'centre_back', 'right_back',
        'defensive_midfielder',
        'left_midfielder', 'centre_midfielder', 'right_midfielder',
        'attacking_midfielder',
        'left_winger', 'right_winger',
        'second_striker', 'striker',
    ]);
    expect($data['preferred_number']['min'])->toBe(1);
    expect($data['preferred_number']['max'])->toBe(99);
    expect($data['availability_default'])->toBe('unknown');
});

it('honours If-None-Match with a 304', function (): void {
    $first = $this->getJson('/api/v1/players/meta')->assertStatus(200);
    $etag = $first->headers->get('ETag');

    expect($etag)->not->toBeNull();

    $this->withHeaders(['If-None-Match' => (string) $etag])
        ->getJson('/api/v1/players/meta')
        ->assertStatus(304);
});

it('changes its ETag when a sourced config key changes', function (): void {
    $before = $this->getJson('/api/v1/players/meta')->headers->get('ETag');

    KxConfig::set(
        'player.positions.labels',
        json_encode(['goalkeeper' => 'Shot Stopper']),
        changeReason: 'test',
    );

    $after = $this->getJson('/api/v1/players/meta')->headers->get('ETag');

    expect($after)->not->toBe($before);
});

it('prefers a locale-suffixed label map when one is configured', function (): void {
    KxConfig::set('player.positions', json_encode(['forward']), changeReason: 'test');
    KxConfig::set(
        'player.positions.labels',
        json_encode(['forward' => 'Forward']),
        changeReason: 'test',
    );
    KxConfig::set(
        'player.positions.labels.fr',
        json_encode(['forward' => 'Attaquant']),
        changeReason: 'test',
    );

    $this->withHeaders(['Accept-Language' => 'fr-FR,fr;q=0.9'])
        ->getJson('/api/v1/players/meta')
        ->assertJsonPath('data.positions.0.label', 'Attaquant')
        // The key never moves with the header.
        ->assertJsonPath('data.positions.0.key', 'forward');

    $this->withHeaders(['Accept-Language' => 'en-GB'])
        ->getJson('/api/v1/players/meta')
        ->assertJsonPath('data.positions.0.label', 'Forward');
});

it('never leaks player or user data', function (): void {
    $body = $this->getJson('/api/v1/players/meta')->getContent();

    foreach (['user_id', 'phone', 'email', 'player_id'] as $forbidden) {
        expect($body)->not->toContain($forbidden);
    }
});
