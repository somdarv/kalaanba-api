<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

function confirmBindHeaders(?string $key = null): array
{
    return [
        'Accept' => 'application/json',
        'Idempotency-Key' => $key ?? (string) Str::uuid(),
    ];
}

function confirmBindSeedToken(string $userId, string $email): string
{
    $plaintext = bin2hex(random_bytes(32));
    DB::table('email_verifications')->insert([
        'id' => (string) Str::uuid(),
        'user_id' => $userId,
        'email' => $email,
        'token_hash' => hash('sha256', $plaintext),
        'purpose' => 'bind_email',
        'expires_at' => now()->addHours(24),
        'consumed_at' => null,
        'created_at' => now(),
    ]);

    return $plaintext;
}

it('confirms a BindEmail token for the authenticated owner and returns the refreshed me view', function (): void {
    $user = User::factory()->withPhone('+233244999111')->create(['email' => null, 'email_verified_at' => null]);
    $plaintext = confirmBindSeedToken((string) $user->getKey(), 'bound@example.test');
    Sanctum::actingAs($user);

    $response = $this->withHeaders(confirmBindHeaders())->postJson('/api/v1/auth/email/verify', [
        'token' => $plaintext,
    ]);

    $response->assertStatus(200)
        ->assertJsonPath('data.email', 'bound@example.test')
        ->assertJsonPath('data.email_verified', true)
        ->assertJsonPath('data.phone_bound', true);

    expect($user->fresh()->email)->toBe('bound@example.test');
    expect(DB::table('outbox_events')
        ->where('event_name', 'identity.user_channel_bound')
        ->where('payload', 'like', '%'.$user->getKey().'%')
        ->count(),
    )->toBe(1);
});

it('returns 403 identity.channel.bind_actor_mismatch when a different user tries to consume the token', function (): void {
    $owner = User::factory()->withPhone('+233244999222')->create(['email' => null]);
    $other = User::factory()->create();
    $plaintext = confirmBindSeedToken((string) $owner->getKey(), 'someone@example.test');
    Sanctum::actingAs($other);

    $response = $this->withHeaders(confirmBindHeaders())->postJson('/api/v1/auth/email/verify', [
        'token' => $plaintext,
    ]);

    $response->assertStatus(403);
});

it('returns 403 when consuming a BindEmail token without auth', function (): void {
    $owner = User::factory()->withPhone('+233244999333')->create(['email' => null]);
    $plaintext = confirmBindSeedToken((string) $owner->getKey(), 'noauth@example.test');

    $response = $this->withHeaders(confirmBindHeaders())->postJson('/api/v1/auth/email/verify', [
        'token' => $plaintext,
    ]);

    $response->assertStatus(403);
});

it('returns 409 when the BindEmail target email gets taken between issuance and confirmation', function (): void {
    $owner = User::factory()->withPhone('+233244999444')->create(['email' => null]);
    $plaintext = confirmBindSeedToken((string) $owner->getKey(), 'race@example.test');
    // Race: another user grabs the same email before this one confirms.
    User::factory()->create(['email' => 'race@example.test']);
    Sanctum::actingAs($owner);

    $response = $this->withHeaders(confirmBindHeaders())->postJson('/api/v1/auth/email/verify', [
        'token' => $plaintext,
    ]);

    $response->assertStatus(409);
});
