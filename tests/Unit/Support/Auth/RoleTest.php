<?php

declare(strict_types=1);

use Kalaanba\Support\Auth\Role;

it('exposes the universal default plus all Build Plan §0.6 roles', function (): void {
    $expected = [
        'user',
        'fan', 'player', 'club_rep', 'club_admin', 'comp_org',
        'referee', 'officiator', 'facility_mgr',
        'hub_admin', 'kalaanba_admin', 'super_admin',
    ];

    $actual = array_map(static fn (Role $r): string => $r->value, Role::cases());

    sort($expected);
    sort($actual);

    expect($actual)->toBe($expected);
});

it('defaults a fresh actor to the universal User role', function (): void {
    expect(Role::default())->toBe(Role::User);
});

it('flags only platform admin tiers as platform admins', function (): void {
    expect(Role::HubAdmin->isPlatformAdmin())->toBeTrue();
    expect(Role::KalaanbaAdmin->isPlatformAdmin())->toBeTrue();
    expect(Role::SuperAdmin->isPlatformAdmin())->toBeTrue();

    expect(Role::User->isPlatformAdmin())->toBeFalse();
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
