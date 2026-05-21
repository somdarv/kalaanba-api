<?php

declare(strict_types=1);

use Kalaanba\Support\Auth\Role;

it('exposes all 11 Build Plan §0.6 roles', function (): void {
    $expected = [
        'fan', 'player', 'club_rep', 'club_admin', 'comp_org',
        'referee', 'officiator', 'facility_mgr',
        'hub_admin', 'kalaanba_admin', 'super_admin',
    ];

    $actual = array_map(static fn (Role $r): string => $r->value, Role::cases());

    sort($expected);
    sort($actual);

    expect($actual)->toBe($expected);
});

it('defaults a fresh actor to Fan', function (): void {
    expect(Role::default())->toBe(Role::Fan);
});

it('flags only platform admin tiers as platform admins', function (): void {
    expect(Role::HubAdmin->isPlatformAdmin())->toBeTrue();
    expect(Role::KalaanbaAdmin->isPlatformAdmin())->toBeTrue();
    expect(Role::SuperAdmin->isPlatformAdmin())->toBeTrue();

    expect(Role::Fan->isPlatformAdmin())->toBeFalse();
    expect(Role::Player->isPlatformAdmin())->toBeFalse();
    expect(Role::ClubAdmin->isPlatformAdmin())->toBeFalse();
    expect(Role::Referee->isPlatformAdmin())->toBeFalse();
});

it('keeps backing values snake_case stable internal keys', function (): void {
    foreach (Role::cases() as $role) {
        expect(preg_match('/^[a-z][a-z0-9_]*$/', $role->value))->toBe(1);
    }
});
