<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Kalaanba\Support\Config;
use Kalaanba\Support\Config\Exceptions\ConfigKeyNotSetException;

uses(RefreshDatabase::class);

it('reads a seeded config value via the facade', function (): void {
    // AdminConfigSeeder plants rp.win = 10
    $value = Config::get('rp.win', 'platform');

    expect($value)->not->toBeNull();
    expect($value->value)->toBe('10');
});

it('returns null when a key has never been set', function (): void {
    expect(Config::get('never.set.key', 'platform'))->toBeNull();
});

it('requires and throws on missing key', function (): void {
    Config::require('never.set.key', 'platform');
})->throws(ConfigKeyNotSetException::class);

it('retrieves history through the facade', function (): void {
    Config::set('facade.history', 'v1', 'platform', changeReason: 'v1');
    sleep(1); // Ensure different effective_from timestamps
    Config::set('facade.history', 'v2', 'platform', changeReason: 'v2');

    $history = Config::history('facade.history', 'platform');

    expect($history)->toHaveCount(2);
    expect($history[0]->version)->toBe(2);
    expect($history[1]->version)->toBe(1);
});

it('sets a config value through the facade', function (): void {
    $value = Config::set('facade.set', 'new_value', 'platform', changeReason: 'via facade');

    expect($value->value)->toBe('new_value');
    expect($value->key)->toBe('facade.set');

    $retrieved = Config::get('facade.set', 'platform');
    expect($retrieved?->value)->toBe('new_value');
});
