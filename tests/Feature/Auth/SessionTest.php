<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Kalaanba\Support\Auth\Role;

uses(RefreshDatabase::class);

function authHeaders(?string $idempotencyKey = null): array
{
    return [
        'Accept' => 'application/json',
        'Idempotency-Key' => $idempotencyKey ?? (string) Str::uuid(),
    ];
}

it('issues a bearer token on valid credentials', function (): void {
    $user = User::factory()->withRole(Role::HubAdmin)->create([
        'email' => 'admin@kalaanba.test',
        'password' => 'secret-pass-1',
    ]);

    $response = $this->withHeaders(authHeaders())->postJson('/api/v1/auth/sessions', [
        'email' => 'admin@kalaanba.test',
        'password' => 'secret-pass-1',
        'device_name' => 'pest',
    ]);

    $response->assertOk()
        ->assertJsonStructure(['data' => ['token', 'token_type', 'expires_at', 'user' => ['id', 'name', 'email', 'role']]])
        ->assertJsonPath('data.user.role', 'hub_admin');

    expect($user->fresh()->tokens()->count())->toBe(1);
});

it('rejects writes without an Idempotency-Key header', function (): void {
    User::factory()->create(['email' => 'a@b.test', 'password' => 'secret-pass-1']);

    $response = $this->withHeaders(['Accept' => 'application/json'])
        ->postJson('/api/v1/auth/sessions', [
            'email' => 'a@b.test',
            'password' => 'secret-pass-1',
        ]);

    $response->assertStatus(400)
        ->assertJsonPath('error.code', 'auth.idempotency_key_required');
});

it('returns 422 on bad credentials', function (): void {
    User::factory()->create(['email' => 'x@y.test', 'password' => 'right-pass']);

    $response = $this->withHeaders(authHeaders())->postJson('/api/v1/auth/sessions', [
        'email' => 'x@y.test',
        'password' => 'wrong-pass',
    ]);

    $response->assertStatus(422);
});

it('blocks archived users from issuing a session', function (): void {
    User::factory()->archived()->create([
        'email' => 'gone@kalaanba.test',
        'password' => 'still-knows-it',
    ]);

    $response = $this->withHeaders(authHeaders())->postJson('/api/v1/auth/sessions', [
        'email' => 'gone@kalaanba.test',
        'password' => 'still-knows-it',
    ]);

    $response->assertStatus(422);
});

it('replays the original status on duplicate Idempotency-Key', function (): void {
    User::factory()->create([
        'email' => 'dup@kalaanba.test',
        'password' => 'secret-pass-1',
    ]);

    $key = (string) Str::uuid();
    $payload = ['email' => 'dup@kalaanba.test', 'password' => 'secret-pass-1'];

    $first = $this->withHeaders(authHeaders($key))->postJson('/api/v1/auth/sessions', $payload);
    $second = $this->withHeaders(authHeaders($key))->postJson('/api/v1/auth/sessions', $payload);

    $first->assertOk();
    $second->assertOk()->assertJsonPath('meta.idempotent_replay', true);
});

it('revokes the current token on DELETE sessions/current', function (): void {
    $user = User::factory()->create();
    $token = $user->createToken('pest')->plainTextToken;

    $response = $this->withHeaders([
        'Accept' => 'application/json',
        'Authorization' => 'Bearer '.$token,
        'Idempotency-Key' => (string) Str::uuid(),
    ])->deleteJson('/api/v1/auth/sessions/current');

    $response->assertNoContent();
    expect($user->fresh()->tokens()->count())->toBe(0);
});
