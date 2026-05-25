<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\App;
use Kalaanba\Support\Auth\Role;

uses(RefreshDatabase::class);

it('bootstraps a Super Admin non-interactively via options', function (): void {
    $this->artisan('godmode:bootstrap', [
        '--email' => 'ops@kalaanba.local',
        '--password' => 'DevPassword123',
        '--name' => 'Ops Operator',
    ])
        ->assertSuccessful();

    $user = User::query()->where('email', 'ops@kalaanba.local')->firstOrFail();

    expect($user->role)->toBe(Role::SuperAdmin)
        ->and($user->name)->toBe('Ops Operator');
});

it('refuses to run in production without --allow-production', function (): void {
    config()->set('app.env', 'production');
    App::detectEnvironment(fn () => 'production');

    $this->artisan('godmode:bootstrap', [
        '--email' => 'prod@kalaanba.local',
        '--password' => 'StrongEnoughPass123',
    ])
        ->assertFailed();

    expect(User::query()->where('email', 'prod@kalaanba.local')->exists())->toBeFalse();
});

it('promotes existing user with --force-reset', function (): void {
    User::factory()->create([
        'email' => 'promote@kalaanba.local',
        'name' => 'Old Name',
    ]);

    $this->artisan('godmode:bootstrap', [
        '--email' => 'promote@kalaanba.local',
        '--password' => 'DevPassword123',
        '--force-reset' => true,
    ])
        ->assertSuccessful();

    $user = User::query()->where('email', 'promote@kalaanba.local')->firstOrFail();

    expect($user->role)->toBe(Role::SuperAdmin);
});
