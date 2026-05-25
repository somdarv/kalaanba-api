<?php

declare(strict_types=1);

use App\Models\User;
use Database\Seeders\SuperAdminSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Kalaanba\Support\Auth\Role;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    // Wipe any leftover SuperAdmin rows from prior live bootstraps that the
    // RefreshDatabase trait may have preserved on the shared dev Postgres DB.
    User::query()->where('role', Role::SuperAdmin->value)->delete();

    config()->set('godmode.seed', [
        'email' => null,
        'password' => null,
        'name' => 'God Mode Operator',
        'force_reset' => false,
        'allow_production' => false,
    ]);
});

it('skips silently when email/password are unset', function (): void {
    $this->seed(SuperAdminSeeder::class);

    expect(User::query()->where('role', Role::SuperAdmin->value)->count())->toBe(0);
});

it('creates a SuperAdmin from config on a fresh database', function (): void {
    config()->set('godmode.seed.email', 'kwame@kalaanba.local');
    config()->set('godmode.seed.password', 'DevPassword123');
    config()->set('godmode.seed.name', 'Kwame Mensah');

    $this->seed(SuperAdminSeeder::class);

    $user = User::query()->where('email', 'kwame@kalaanba.local')->firstOrFail();

    expect($user->name)->toBe('Kwame Mensah')
        ->and($user->role)->toBe(Role::SuperAdmin)
        ->and($user->archived_at)->toBeNull()
        ->and($user->email_verified_at)->not->toBeNull();
});

it('leaves an existing user untouched without force-reset', function (): void {
    config()->set('godmode.seed.email', 'existing@kalaanba.local');
    config()->set('godmode.seed.password', 'DevPassword123');
    config()->set('godmode.seed.force_reset', false);

    $original = User::factory()->create([
        'email' => 'existing@kalaanba.local',
        'name' => 'Pre-existing',
    ]);

    $this->seed(SuperAdminSeeder::class);

    $user = User::query()->where('email', 'existing@kalaanba.local')->firstOrFail();

    expect($user->name)->toBe('Pre-existing')
        ->and($user->role)->toBe($original->role);
});

it('promotes and resets an existing user when force-reset is true', function (): void {
    config()->set('godmode.seed.email', 'promote@kalaanba.local');
    config()->set('godmode.seed.password', 'DevPassword123');
    config()->set('godmode.seed.force_reset', true);

    User::factory()->create([
        'email' => 'promote@kalaanba.local',
        'name' => 'Old Name',
    ]);

    $this->seed(SuperAdminSeeder::class);

    $user = User::query()->where('email', 'promote@kalaanba.local')->firstOrFail();

    expect($user->role)->toBe(Role::SuperAdmin)
        ->and($user->archived_at)->toBeNull();
});

it('rejects a short password', function (): void {
    config()->set('godmode.seed.email', 'weak@kalaanba.local');
    config()->set('godmode.seed.password', 'short');

    expect(fn () => $this->seed(SuperAdminSeeder::class))
        ->toThrow(RuntimeException::class, 'at least 8 characters');
});

it('is idempotent for an existing SuperAdmin', function (): void {
    config()->set('godmode.seed.email', 'super@kalaanba.local');
    config()->set('godmode.seed.password', 'DevPassword123');

    $this->seed(SuperAdminSeeder::class);
    $firstId = User::query()->where('email', 'super@kalaanba.local')->value('id');

    $this->seed(SuperAdminSeeder::class);
    $secondId = User::query()->where('email', 'super@kalaanba.local')->value('id');

    expect($secondId)->toBe($firstId)
        ->and(User::query()->where('email', 'super@kalaanba.local')->count())->toBe(1);
});
