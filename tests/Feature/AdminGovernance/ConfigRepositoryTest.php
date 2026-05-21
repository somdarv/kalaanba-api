<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Kalaanba\Support\Config\Contracts\ConfigRepository;
use Kalaanba\Support\Config\Exceptions\ConfigKeyNotSetException;

uses(RefreshDatabase::class);

it('stores and retrieves a config value', function (): void {
    /** @var ConfigRepository $repo */
    $repo = app(ConfigRepository::class);

    $value = $repo->set('test.key', 'test_value', 'platform', changeReason: 'test seed');

    expect($value->key)->toBe('test.key');
    expect($value->value)->toBe('test_value');
    expect($value->version)->toBe(1);
});

it('increments version on update', function (): void {
    /** @var ConfigRepository $repo */
    $repo = app(ConfigRepository::class);

    $v1 = $repo->set('versioned.key', '1', 'platform', changeReason: 'v1');
    $v2 = $repo->set('versioned.key', '2', 'platform', changeReason: 'v2');

    expect($v1->version)->toBe(1);
    expect($v2->version)->toBe(2);
});

it('retrieves the most recent effective value', function (): void {
    /** @var ConfigRepository $repo */
    $repo = app(ConfigRepository::class);

    $repo->set('effective.key', 'old', 'platform', changeReason: 'old value');
    $newest = $repo->set('effective.key', 'new', 'platform', changeReason: 'new value');

    $retrieved = $repo->get('effective.key', 'platform');

    expect($retrieved?->value)->toBe('new');
    expect($retrieved?->version)->toBe(2);
});

it('returns null for keys that have never been set', function (): void {
    /** @var ConfigRepository $repo */
    $repo = app(ConfigRepository::class);

    expect($repo->get('never.set.key', 'platform'))->toBeNull();
});

it('scopes config by scope and scope_id', function (): void {
    /** @var ConfigRepository $repo */
    $repo = app(ConfigRepository::class);

    $platformValue = $repo->set('scoped.key', 'platform_value', 'platform', changeReason: 'platform');
    $hubValue = $repo->set('scoped.key', 'hub_value', 'hub', 'hub-uuid-1', changeReason: 'hub');

    expect($repo->get('scoped.key', 'platform')?->value)->toBe('platform_value');
    expect($repo->get('scoped.key', 'hub', 'hub-uuid-1')?->value)->toBe('hub_value');
});

it('respects effective_from dates for time-travel reads', function (): void {
    /** @var ConfigRepository $repo */
    $repo = app(ConfigRepository::class);

    $past = new DateTimeImmutable('2026-01-01 10:00:00', new DateTimeZone('UTC'));
    $now = new DateTimeImmutable('now', new DateTimeZone('UTC'));

    // Manually insert a past value
    DB::table('admin_config')->insert([
        'key' => 'time_travel.key',
        'scope' => 'platform',
        'scope_id' => null,
        'value' => 'past_value',
        'effective_from' => $past->format('Y-m-d H:i:s'),
        'version' => 1,
        'approval_level' => 'low',
        'change_reason' => 'inserted for test',
        'created_at' => $now->format('Y-m-d H:i:s'),
        'updated_at' => $now->format('Y-m-d H:i:s'),
    ]);

    // Insert a newer value
    $repo->set('time_travel.key', 'current_value', 'platform', changeReason: 'current');

    // Read at past date — should get the old value
    $historical = $repo->get('time_travel.key', 'platform', at: $past->modify('+1 second'));
    expect($historical?->value)->toBe('past_value');

    // Read at now — should get the new value
    $current = $repo->get('time_travel.key', 'platform');
    expect($current?->value)->toBe('current_value');
});

it('maintains history in reverse chronological order', function (): void {
    /** @var ConfigRepository $repo */
    $repo = app(ConfigRepository::class);

    $v1 = $repo->set('history.key', '1', 'platform', changeReason: 'v1');
    sleep(1);
    $v2 = $repo->set('history.key', '2', 'platform', changeReason: 'v2');
    sleep(1);
    $v3 = $repo->set('history.key', '3', 'platform', changeReason: 'v3');

    $history = $repo->history('history.key', 'platform');

    expect($history)->toHaveCount(3);
    expect($history[0]->version)->toBe(3); // most recent first
    expect($history[1]->version)->toBe(2);
    expect($history[2]->version)->toBe(1);
    expect($history[0]->value)->toBe('3');
});

it('throws ConfigKeyNotSetException when requiring an unset key', function (): void {
    /** @var ConfigRepository $repo */
    $repo = app(ConfigRepository::class);

    $repo->require('never.set.key', 'platform');
})->throws(ConfigKeyNotSetException::class);

it('stores metadata: approved_by, approval_level, change_reason', function (): void {
    /** @var ConfigRepository $repo */
    $repo = app(ConfigRepository::class);

    $uuid = '12345678-1234-5678-1234-567812345678';
    $value = $repo->set(
        'metadata.key',
        'value',
        'platform',
        approvedBy: $uuid,
        approvalLevel: 'critical',
        changeReason: 'Security audit raised thresholds',
    );

    expect($value->approvedBy)->toBe($uuid);
    expect($value->approvalLevel)->toBe('critical');
    expect($value->changeReason)->toBe('Security audit raised thresholds');
});
