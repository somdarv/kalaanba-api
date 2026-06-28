<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

function emailRegHeaders(?string $key = null): array
{
    return [
        'Accept' => 'application/json',
        'Idempotency-Key' => $key ?? (string) Str::uuid(),
    ];
}

function emailRegSeedArea(): string
{
    $countryId = (string) Str::uuid();
    DB::table('countries')->insert([
        'id' => $countryId,
        'code' => strtoupper(substr(uniqid(), -2)),
        'name' => 'EmailReg Country '.uniqid(),
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    $regionId = (string) Str::uuid();
    DB::table('regions')->insert([
        'id' => $regionId,
        'country_id' => $countryId,
        'code' => 'er-'.substr(uniqid(), -6),
        'name' => 'EmailReg Region',
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    $hubId = (string) Str::uuid();
    DB::table('city_hubs')->insert([
        'id' => $hubId,
        'region_id' => $regionId,
        'code' => 'eh-'.substr(uniqid(), -6),
        'name' => 'EmailReg Hub',
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    $zoneId = (string) Str::uuid();
    DB::table('zones')->insert([
        'id' => $zoneId,
        'city_hub_id' => $hubId,
        'kind' => 'zone',
        'code' => 'email-reg-zone-'.substr(uniqid(), -6),
        'name' => 'EmailReg Zone',
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    $areaId = (string) Str::uuid();
    DB::table('areas')->insert([
        'id' => $areaId,
        'zone_id' => $zoneId,
        'code' => 'ea-'.substr($areaId, 0, 8),
        'name' => 'EmailReg Area',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    return $areaId;
}

it('registers via email channel and persists an email_verifications row (status 202)', function (): void {
    $areaId = emailRegSeedArea();

    $response = $this->withHeaders(emailRegHeaders())->postJson('/api/v1/auth/registration', [
        'channel' => 'email',
        'name' => 'Ada Lovelace',
        'area_id' => $areaId,
        'email' => 'ada@example.test',
        'password' => 'Strong-Pass-1',
    ]);

    $response->assertStatus(202)
        ->assertJsonPath('data.status', 'email_verification_pending')
        ->assertJsonStructure(['data' => ['status', 'user_id', 'expires_at']]);

    $userId = $response->json('data.user_id');
    $user = User::query()->find($userId);
    expect($user)->not->toBeNull();
    expect($user->email)->toBe('ada@example.test');
    expect($user->email_verified_at)->toBeNull();
    expect($user->claimed_at)->toBeNull();
    expect(Hash::check('Strong-Pass-1', $user->password))->toBeTrue();

    expect(DB::table('email_verifications')->where('user_id', $userId)->where('purpose', 'registration')->count())->toBe(1);
    expect(DB::table('outbox_events')->where('event_name', 'identity.user_registered')->count())->toBe(1);
    // CLAIMED transition only fires after verification — engine doc §7.1.
    expect(DB::table('outbox_events')->where('event_name', 'identity.user_claimed')->count())->toBe(0);
});

it('rejects email registration when the email is already in use with 409', function (): void {
    $areaId = emailRegSeedArea();
    User::factory()->create(['email' => 'taken@example.test']);

    $response = $this->withHeaders(emailRegHeaders())->postJson('/api/v1/auth/registration', [
        'channel' => 'email',
        'name' => 'Dup Email',
        'area_id' => $areaId,
        'email' => 'taken@example.test',
        'password' => 'Strong-Pass-1',
    ]);

    $response->assertStatus(409);
});

it('rejects weak passwords with the configured policy violations', function (): void {
    $areaId = emailRegSeedArea();

    $response = $this->withHeaders(emailRegHeaders())->postJson('/api/v1/auth/registration', [
        'channel' => 'email',
        'name' => 'Weak Pass',
        'area_id' => $areaId,
        'email' => 'weak@example.test',
        'password' => 'short',
    ]);

    $response->assertStatus(422)
        ->assertJsonPath('errors.password.0', 'auth.password.too_short');
});

it('blocks login on an unverified email account', function (): void {
    User::factory()->unverified()->create([
        'email' => 'pending@example.test',
        'password' => 'still-knows-it',
    ]);

    $response = $this->withHeaders(emailRegHeaders())->postJson('/api/v1/auth/sessions', [
        'email' => 'pending@example.test',
        'password' => 'still-knows-it',
    ]);

    $response->assertStatus(422)
        ->assertJsonPath('errors.email.0', 'auth.email.not_verified');
});
