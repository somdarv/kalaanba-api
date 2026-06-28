<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

function emailConfirmHeaders(?string $key = null): array
{
    return [
        'Accept' => 'application/json',
        'Idempotency-Key' => $key ?? (string) Str::uuid(),
    ];
}

function emailConfirmSeedToken(string $userId, string $email, string $purpose = 'registration', int $ttlHours = 24, ?string $consumedAt = null): string
{
    $plaintext = bin2hex(random_bytes(32));
    DB::table('email_verifications')->insert([
        'id' => (string) Str::uuid(),
        'user_id' => $userId,
        'email' => $email,
        'token_hash' => hash('sha256', $plaintext),
        'purpose' => $purpose,
        'expires_at' => now()->addHours($ttlHours),
        'consumed_at' => $consumedAt,
        'created_at' => now(),
    ]);

    return $plaintext;
}

it('confirms a Registration token, marks user CLAIMED, and mints a session', function (): void {
    $user = User::factory()->unverified()->create([
        'email' => 'pending@example.test',
        'claimed_at' => null,
    ]);
    $plaintext = emailConfirmSeedToken((string) $user->getKey(), 'pending@example.test');

    $response = $this->withHeaders(emailConfirmHeaders())->postJson('/api/v1/auth/email/verify', [
        'token' => $plaintext,
        'device_name' => 'pest',
    ]);

    $response->assertStatus(200)
        ->assertJsonStructure(['data' => ['token', 'expires_at', 'user' => ['id', 'name']]]);

    $fresh = $user->fresh();
    expect($fresh->email_verified_at)->not->toBeNull();
    expect($fresh->claimed_at)->not->toBeNull();

    expect(DB::table('email_verifications')->where('user_id', $user->getKey())->whereNotNull('consumed_at')->count())->toBe(1);
    expect(DB::table('outbox_events')->where('event_name', 'identity.user_claimed')->count())->toBe(1);
});

it('rejects an unknown token with auth.email_verify.token_unknown', function (): void {
    // Well-formed (64 hex chars) but never persisted — exercises the handler's
    // unknown-token branch rather than the FormRequest min-length rule.
    $response = $this->withHeaders(emailConfirmHeaders())->postJson('/api/v1/auth/email/verify', [
        'token' => bin2hex(random_bytes(32)),
    ]);

    $response->assertStatus(422)
        ->assertJsonPath('errors.token.0', 'auth.email_verify.token_unknown');
});

it('rejects an expired token', function (): void {
    $user = User::factory()->unverified()->create(['email' => 'exp@example.test']);
    $plaintext = emailConfirmSeedToken((string) $user->getKey(), 'exp@example.test', 'registration', -1);

    $response = $this->withHeaders(emailConfirmHeaders())->postJson('/api/v1/auth/email/verify', [
        'token' => $plaintext,
    ]);

    $response->assertStatus(422)
        ->assertJsonPath('errors.token.0', 'auth.email_verify.token_expired');
});

it('rejects a consumed token (single-use)', function (): void {
    $user = User::factory()->unverified()->create(['email' => 'used@example.test']);
    $plaintext = emailConfirmSeedToken((string) $user->getKey(), 'used@example.test', 'registration', 24, now()->toDateTimeString());

    $response = $this->withHeaders(emailConfirmHeaders())->postJson('/api/v1/auth/email/verify', [
        'token' => $plaintext,
    ]);

    $response->assertStatus(422)
        ->assertJsonPath('errors.token.0', 'auth.email_verify.token_consumed');
});
