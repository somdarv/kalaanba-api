<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Kalaanba\Support\Auth\Otp\MockOtpProvider;
use Kalaanba\Support\Auth\Otp\OtpProvider;
use Kalaanba\Support\Auth\PhoneHash;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->mockProvider = new MockOtpProvider;
    $this->app->instance(OtpProvider::class, $this->mockProvider);
    $this->app->instance(MockOtpProvider::class, $this->mockProvider);
});

function bindPhoneHeaders(?string $key = null): array
{
    return [
        'Accept' => 'application/json',
        'Idempotency-Key' => $key ?? (string) Str::uuid(),
    ];
}

it('starts a phone-channel bind for an authenticated user and returns 202 with masked phone', function (): void {
    $user = User::factory()->create();
    Sanctum::actingAs($user);

    $response = $this->withHeaders(bindPhoneHeaders())->postJson('/api/v1/users/me/channels/phone', [
        'phone_e164' => '+233244777111',
    ]);

    $response->assertStatus(202)
        ->assertJsonStructure(['data' => ['expires_at', 'masked_phone', 'otp_length']])
        ->assertJsonPath('data.masked_phone', fn (string $m) => str_ends_with($m, '7111'));

    expect($this->mockProvider->lastSent())->not->toBeNull();
});

it('rejects start-phone-bind with 409 when the phone is already bound to another account', function (): void {
    $user = User::factory()->create();
    User::factory()->withPhone('+233244777222')->create();
    Sanctum::actingAs($user);

    $response = $this->withHeaders(bindPhoneHeaders())->postJson('/api/v1/users/me/channels/phone', [
        'phone_e164' => '+233244777222',
    ]);

    $response->assertStatus(409);
});

it('confirms a phone-channel bind, persists the hash, and emits identity.user_channel_bound', function (): void {
    $user = User::factory()->create();
    Sanctum::actingAs($user);
    $phone = '+233244777333';

    $this->withHeaders(bindPhoneHeaders())->postJson('/api/v1/users/me/channels/phone', [
        'phone_e164' => $phone,
    ])->assertStatus(202);
    $code = (string) $this->mockProvider->lastSent()['code'];

    $response = $this->withHeaders(bindPhoneHeaders())->postJson('/api/v1/users/me/channels/phone/confirm', [
        'phone_e164' => $phone,
        'otp' => $code,
    ]);

    $response->assertNoContent();

    $fresh = $user->fresh();
    expect($fresh->phone_e164_hash)->toBe(app(PhoneHash::class)->hash($phone));
    expect(DB::table('outbox_events')
        ->where('event_name', 'identity.user_channel_bound')
        ->where('payload', 'like', '%'.$user->getKey().'%')
        ->count(),
    )->toBe(1);
});

it('rejects confirm-phone-bind without auth', function (): void {
    $response = $this->withHeaders(bindPhoneHeaders())->postJson('/api/v1/users/me/channels/phone/confirm', [
        'phone_e164' => '+233244777444',
        'otp' => '123456',
    ]);

    $response->assertStatus(401);
});
