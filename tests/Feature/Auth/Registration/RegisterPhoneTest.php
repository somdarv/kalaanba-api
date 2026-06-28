<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Kalaanba\Support\Auth\Otp\MockOtpProvider;
use Kalaanba\Support\Auth\Otp\OtpProvider;
use Kalaanba\Support\Auth\PhoneHash;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->mockProvider = new MockOtpProvider;
    $this->app->instance(OtpProvider::class, $this->mockProvider);
    $this->app->instance(MockOtpProvider::class, $this->mockProvider);
});

function phoneRegHeaders(?string $key = null): array
{
    return [
        'Accept' => 'application/json',
        'Idempotency-Key' => $key ?? (string) Str::uuid(),
    ];
}

function phoneRegSeedArea(): string
{
    $countryId = (string) Str::uuid();
    DB::table('countries')->insert([
        'id' => $countryId,
        'code' => strtoupper(substr(uniqid(), -2)),
        'name' => 'Reg Country '.uniqid(),
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    $regionId = (string) Str::uuid();
    DB::table('regions')->insert([
        'id' => $regionId,
        'country_id' => $countryId,
        'code' => 'rr-'.substr(uniqid(), -6),
        'name' => 'Reg Region',
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    $hubId = (string) Str::uuid();
    DB::table('city_hubs')->insert([
        'id' => $hubId,
        'region_id' => $regionId,
        'code' => 'rh-'.substr(uniqid(), -6),
        'name' => 'Reg Hub',
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    $zoneId = (string) Str::uuid();
    DB::table('zones')->insert([
        'id' => $zoneId,
        'city_hub_id' => $hubId,
        'kind' => 'zone',
        'code' => 'reg-zone-'.substr(uniqid(), -6),
        'name' => 'Reg Zone',
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    $areaId = (string) Str::uuid();
    DB::table('areas')->insert([
        'id' => $areaId,
        'zone_id' => $zoneId,
        'code' => 'ra-'.substr($areaId, 0, 8),
        'name' => 'Reg Area',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    return $areaId;
}

it('registers a new user via the phone channel and mints a session', function (): void {
    $areaId = phoneRegSeedArea();
    $phone = '+233244555111';

    // Step 1 — request an OTP via the existing public endpoint.
    $this->withHeaders(phoneRegHeaders())
        ->postJson('/api/v1/auth/otp/request', ['phone_e164' => $phone])
        ->assertStatus(202);

    $code = (string) $this->mockProvider->lastSent()['code'];

    // Step 2 — submit registration with that OTP.
    $response = $this->withHeaders(phoneRegHeaders())->postJson('/api/v1/auth/registration', [
        'channel' => 'phone',
        'name' => 'Kojo Mensah',
        'area_id' => $areaId,
        'phone_e164' => $phone,
        'otp' => $code,
        'device_name' => 'pest',
    ]);

    $response->assertStatus(200)
        ->assertJsonStructure(['data' => ['token', 'expires_at', 'user' => ['id', 'name', 'role']]])
        ->assertJsonPath('data.user.name', 'Kojo Mensah');

    $created = User::query()->where('name', 'Kojo Mensah')->firstOrFail();
    expect($created->phone_e164_hash)->toBe(app(PhoneHash::class)->hash($phone));
    expect($created->claimed_at)->not->toBeNull();
    expect($created->email)->toBeNull();

    expect(DB::table('outbox_events')->where('event_name', 'identity.user_registered')->count())->toBe(1);
    expect(DB::table('outbox_events')->where('event_name', 'identity.user_claimed')->count())->toBe(1);
});

it('registers without an area_id — area is deferred to the profile screen', function (): void {
    $phone = '+233244555666';

    $this->withHeaders(phoneRegHeaders())
        ->postJson('/api/v1/auth/otp/request', ['phone_e164' => $phone])
        ->assertStatus(202);
    $code = (string) $this->mockProvider->lastSent()['code'];

    $response = $this->withHeaders(phoneRegHeaders())->postJson('/api/v1/auth/registration', [
        'channel' => 'phone',
        'name' => 'Area Later',
        'phone_e164' => $phone,
        'otp' => $code,
    ]);

    $response->assertStatus(200)
        ->assertJsonPath('data.user.name', 'Area Later');

    $created = User::query()->where('name', 'Area Later')->firstOrFail();
    expect($created->area_id)->toBeNull();
    expect($created->claimed_at)->not->toBeNull();
});

it('rejects phone registration when the phone is already in use with 409', function (): void {
    $areaId = phoneRegSeedArea();
    $phone = '+233244555222';
    User::factory()->withPhone($phone)->create();

    $this->withHeaders(phoneRegHeaders())
        ->postJson('/api/v1/auth/otp/request', ['phone_e164' => $phone])
        ->assertStatus(202);
    $code = (string) $this->mockProvider->lastSent()['code'];

    $response = $this->withHeaders(phoneRegHeaders())->postJson('/api/v1/auth/registration', [
        'channel' => 'phone',
        'name' => 'Dup Phone',
        'area_id' => $areaId,
        'phone_e164' => $phone,
        'otp' => $code,
    ]);

    $response->assertStatus(409);
});

it('rejects registration when the supplied area_id does not exist', function (): void {
    $phone = '+233244555333';
    $this->withHeaders(phoneRegHeaders())
        ->postJson('/api/v1/auth/otp/request', ['phone_e164' => $phone])
        ->assertStatus(202);
    $code = (string) $this->mockProvider->lastSent()['code'];

    $response = $this->withHeaders(phoneRegHeaders())->postJson('/api/v1/auth/registration', [
        'channel' => 'phone',
        'name' => 'Bad Area',
        'area_id' => (string) Str::uuid(),
        'phone_e164' => $phone,
        'otp' => $code,
    ]);

    $response->assertStatus(422)
        ->assertJsonPath('errors.area_id.0', 'profile.area_not_found');
});

it('rejects registration without an Idempotency-Key', function (): void {
    $areaId = phoneRegSeedArea();

    $response = $this->withHeaders(['Accept' => 'application/json'])
        ->postJson('/api/v1/auth/registration', [
            'channel' => 'phone',
            'name' => 'No Key',
            'area_id' => $areaId,
            'phone_e164' => '+233244555444',
            'otp' => '123456',
        ]);

    $response->assertStatus(400)
        ->assertJsonPath('error.code', 'auth.idempotency_key_required');
});

it('drops unknown attributes — `role` cannot be injected at registration', function (): void {
    $areaId = phoneRegSeedArea();
    $phone = '+233244555555';

    $this->withHeaders(phoneRegHeaders())
        ->postJson('/api/v1/auth/otp/request', ['phone_e164' => $phone])
        ->assertStatus(202);
    $code = (string) $this->mockProvider->lastSent()['code'];

    $this->withHeaders(phoneRegHeaders())->postJson('/api/v1/auth/registration', [
        'channel' => 'phone',
        'name' => 'No Privilege Escalation',
        'area_id' => $areaId,
        'phone_e164' => $phone,
        'otp' => $code,
        'role' => 'super_admin',
    ])->assertStatus(200);

    $created = User::query()->where('name', 'No Privilege Escalation')->firstOrFail();
    expect($created->role)->not->toBe('super_admin');
});
