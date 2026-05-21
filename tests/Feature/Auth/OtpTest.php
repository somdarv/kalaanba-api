<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Kalaanba\Support\Auth\Otp\MockOtpProvider;
use Kalaanba\Support\Auth\Otp\OtpProvider;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->mockProvider = new MockOtpProvider;
    $this->app->instance(OtpProvider::class, $this->mockProvider);
    $this->app->instance(MockOtpProvider::class, $this->mockProvider);
    RateLimiter::clear('otp');
});

function otpHeaders(?string $idempotencyKey = null): array
{
    return [
        'Accept' => 'application/json',
        'Idempotency-Key' => $idempotencyKey ?? (string) Str::uuid(),
    ];
}

it('issues an OTP and exposes the masked phone + expiry', function (): void {
    $response = $this->withHeaders(otpHeaders())
        ->postJson('/api/v1/auth/otp/request', ['phone_e164' => '+233244123456']);

    $response->assertStatus(202)
        ->assertJsonStructure(['data' => ['expires_at', 'masked_phone', 'otp_length']])
        ->assertJsonPath('data.otp_length', 6)
        ->assertJsonPath('data.masked_phone', fn (string $masked) => str_ends_with($masked, '3456'));

    expect($this->mockProvider->lastSent())->not->toBeNull();
});

it('rejects OTP requests without an Idempotency-Key', function (): void {
    $response = $this->withHeaders(['Accept' => 'application/json'])
        ->postJson('/api/v1/auth/otp/request', ['phone_e164' => '+233244123456']);

    $response->assertStatus(400)
        ->assertJsonPath('error.code', 'auth.idempotency_key_required');
});

it('rejects phones that are not E.164', function (): void {
    $response = $this->withHeaders(otpHeaders())
        ->postJson('/api/v1/auth/otp/request', ['phone_e164' => '0244123456']);

    $response->assertStatus(422);
});

it('rate-limits OTP requests to 5 per minute per phone+ip', function (): void {
    for ($i = 0; $i < 5; $i++) {
        $this->withHeaders(otpHeaders())
            ->postJson('/api/v1/auth/otp/request', ['phone_e164' => '+233244111111'])
            ->assertStatus(202);
    }

    $this->withHeaders(otpHeaders())
        ->postJson('/api/v1/auth/otp/request', ['phone_e164' => '+233244111111'])
        ->assertStatus(429);
});

it('verifies a valid code and returns a Sanctum session', function (): void {
    $user = User::factory()->withPhone('+233244123456')->create();

    $this->withHeaders(otpHeaders())
        ->postJson('/api/v1/auth/otp/request', ['phone_e164' => '+233244123456'])
        ->assertStatus(202);

    $code = (string) $this->mockProvider->lastSent()['code'];

    $response = $this->withHeaders(otpHeaders())->postJson('/api/v1/auth/otp/verify', [
        'phone_e164' => '+233244123456',
        'otp' => $code,
        'device_name' => 'pest',
    ]);

    $response->assertOk()
        ->assertJsonStructure(['data' => ['token', 'expires_at', 'user' => ['id', 'email']]]);

    expect($user->fresh()->tokens()->count())->toBe(1);
});

it('rejects an unknown phone with auth.otp_not_found', function (): void {
    $this->withHeaders(otpHeaders())
        ->postJson('/api/v1/auth/otp/request', ['phone_e164' => '+233244999999'])
        ->assertStatus(202);

    $code = (string) $this->mockProvider->lastSent()['code'];

    $response = $this->withHeaders(otpHeaders())->postJson('/api/v1/auth/otp/verify', [
        'phone_e164' => '+233244999999',
        'otp' => $code,
    ]);

    $response->assertStatus(422)
        ->assertJsonPath('errors.phone_e164.0', 'auth.otp_not_found');
});

it('rejects a wrong code with auth.otp_invalid', function (): void {
    User::factory()->withPhone('+233244123456')->create();

    $this->withHeaders(otpHeaders())
        ->postJson('/api/v1/auth/otp/request', ['phone_e164' => '+233244123456'])
        ->assertStatus(202);

    $response = $this->withHeaders(otpHeaders())->postJson('/api/v1/auth/otp/verify', [
        'phone_e164' => '+233244123456',
        'otp' => '000000',
    ]);

    $response->assertStatus(422)
        ->assertJsonPath('errors.otp.0', 'auth.otp_invalid');
});

it('blocks archived users at verify-time', function (): void {
    User::factory()->archived()->withPhone('+233244123456')->create();

    $this->withHeaders(otpHeaders())
        ->postJson('/api/v1/auth/otp/request', ['phone_e164' => '+233244123456'])
        ->assertStatus(202);

    $code = (string) $this->mockProvider->lastSent()['code'];

    $this->withHeaders(otpHeaders())->postJson('/api/v1/auth/otp/verify', [
        'phone_e164' => '+233244123456',
        'otp' => $code,
    ])->assertStatus(422)
        ->assertJsonPath('errors.phone_e164.0', 'auth.otp_not_found');
});
