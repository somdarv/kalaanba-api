<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

function bindEmailHeaders(?string $key = null): array
{
    return [
        'Accept' => 'application/json',
        'Idempotency-Key' => $key ?? (string) Str::uuid(),
    ];
}

it('starts an email-channel bind for an authenticated user and persists a bind_email verification', function (): void {
    $user = User::factory()->withPhone('+233244888111')->create(['email' => null, 'email_verified_at' => null]);
    Sanctum::actingAs($user);

    $response = $this->withHeaders(bindEmailHeaders())->postJson('/api/v1/users/me/channels/email', [
        'email' => 'new@example.test',
    ]);

    $response->assertStatus(202)
        ->assertJsonPath('data.status', 'email_verification_pending')
        ->assertJsonStructure(['data' => ['status', 'expires_at']]);

    expect(DB::table('email_verifications')
        ->where('user_id', $user->getKey())
        ->where('purpose', 'bind_email')
        ->where('email', 'new@example.test')
        ->count(),
    )->toBe(1);
});

it('rejects start-email-bind with 409 when the email is already in use', function (): void {
    $user = User::factory()->create();
    User::factory()->create(['email' => 'already@example.test']);
    Sanctum::actingAs($user);

    $response = $this->withHeaders(bindEmailHeaders())->postJson('/api/v1/users/me/channels/email', [
        'email' => 'already@example.test',
    ]);

    $response->assertStatus(409);
});

it('rejects start-email-bind without auth', function (): void {
    $response = $this->withHeaders(bindEmailHeaders())->postJson('/api/v1/users/me/channels/email', [
        'email' => 'someone@example.test',
    ]);

    $response->assertStatus(401);
});
