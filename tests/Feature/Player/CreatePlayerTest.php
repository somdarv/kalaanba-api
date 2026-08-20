<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

/**
 * @param  array<string, string>  $extra
 * @return array<string, string>
 */
function playerIdem(array $extra = []): array
{
    return array_merge(['Idempotency-Key' => (string) Str::uuid()], $extra);
}

/**
 * @return array<string, mixed>
 */
function validPlayerPayload(array $overrides = []): array
{
    return array_merge([
        'first_name' => 'Abdul',
        'last_name' => 'Rahman',
        'stage_name' => 'Kaka',
        'preferred_number' => 10,
        'primary_position' => 'striker',
    ], $overrides);
}

it('rejects unauthenticated callers with 401', function (): void {
    $this->postJson('/api/v1/players', validPlayerPayload(), playerIdem())
        ->assertStatus(401);
});

it('creates a claimed free-agent player and returns 201', function (): void {
    Sanctum::actingAs(User::factory()->create(), ['*']);

    $this->postJson('/api/v1/players', validPlayerPayload(), playerIdem())
        ->assertStatus(201)
        ->assertJsonPath('data.stage_name', 'Kaka')
        ->assertJsonPath('data.preferred_number', 10)
        ->assertJsonPath('data.primary_position', 'striker')
        ->assertJsonPath('data.market_status', 'free_agent')
        ->assertJsonPath('data.claim_status', 'claimed');

    expect(DB::table('players')->count())->toBe(1);
});

it('defaults availability to unknown when omitted', function (): void {
    Sanctum::actingAs(User::factory()->create(), ['*']);

    $this->postJson('/api/v1/players', validPlayerPayload(), playerIdem())
        ->assertStatus(201)
        ->assertJsonPath('data.availability_status', 'unknown');
});

it('is one-per-user — a second submission returns the existing player with 200', function (): void {
    Sanctum::actingAs($user = User::factory()->create(), ['*']);

    $first = $this->postJson('/api/v1/players', validPlayerPayload(), playerIdem())
        ->assertStatus(201)
        ->json('data.id');

    $this->postJson('/api/v1/players', validPlayerPayload(['stage_name' => 'Different']), playerIdem())
        ->assertStatus(200)
        ->assertJsonPath('data.id', $first)
        ->assertJsonPath('data.stage_name', 'Kaka'); // unchanged — existing returned

    expect(DB::table('players')->where('user_id', $user->getAuthIdentifier())->count())->toBe(1);
});

it('rejects an unknown primary position with 422', function (): void {
    Sanctum::actingAs(User::factory()->create(), ['*']);

    $this->postJson('/api/v1/players', validPlayerPayload(['primary_position' => 'sweeper']), playerIdem())
        ->assertStatus(422)
        ->assertJsonValidationErrors(['primary_position']);
});

it('rejects a preferred number out of range with 422', function (): void {
    Sanctum::actingAs(User::factory()->create(), ['*']);

    $this->postJson('/api/v1/players', validPlayerPayload(['preferred_number' => 200]), playerIdem())
        ->assertStatus(422)
        ->assertJsonValidationErrors(['preferred_number']);
});

it('creates a player without optional fields (headshot/number/position)', function (): void {
    Sanctum::actingAs(User::factory()->create(), ['*']);

    $this->postJson('/api/v1/players', [
        'first_name' => 'Yaw',
        'last_name' => 'Mensah',
        'stage_name' => 'YM',
    ], playerIdem())
        ->assertStatus(201)
        ->assertJsonPath('data.preferred_number', null)
        ->assertJsonPath('data.primary_position', null)
        ->assertJsonPath('data.headshot_url', null);
});
