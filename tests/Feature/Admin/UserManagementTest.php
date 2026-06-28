<?php

declare(strict_types=1);

use App\Models\Admin\AdminAccessCode;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Kalaanba\Support\Auth\Otp\MockOtpProvider;
use Kalaanba\Support\Auth\Otp\OtpProvider;
use Kalaanba\Support\Auth\Role;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    AdminAccessCode::query()->updateOrCreate(
        ['label' => AdminAccessCode::USERS_SECTION],
        ['code_hash' => Hash::make('023050')],
    );
    $this->mockOtp = new MockOtpProvider;
    $this->app->instance(OtpProvider::class, $this->mockOtp);

    $this->super = User::factory()->withRole(Role::SuperAdmin)->create();
    Sanctum::actingAs($this->super, ['*']);
});

function idem(array $extra = []): array
{
    return array_merge(['Idempotency-Key' => (string) Str::uuid()], $extra);
}

it('lists users without exposing any secret', function (): void {
    User::factory()->withPhone('+233244123456')->create([
        'name' => 'Kojo Tester',
        'email' => 'kojo@example.com',
    ]);

    $res = $this->getJson('/api/v1/admin/users')->assertOk()
        ->assertJsonStructure(['data' => [['id', 'name', 'phone_masked', 'auth_method', 'status']], 'meta']);

    $body = $res->getContent();
    expect($body)->not->toContain('password');
    expect($body)->not->toContain('phone_e164_hash');
    // Full number never present — only the masked last-4.
    expect($body)->toContain('3456')->and($body)->not->toContain('+233244123456');
});

it('rejects non-super-admins', function (): void {
    Sanctum::actingAs(User::factory()->withRole(Role::HubAdmin)->create(), ['*']);
    $this->getJson('/api/v1/admin/users')->assertStatus(403);
});

it('refuses set-password without the access code', function (): void {
    $user = User::factory()->create();

    $this->postJson("/api/v1/admin/users/{$user->id}/password", [
        'password' => 'brandnew-pass-1',
    ], idem())->assertStatus(403)->assertJsonPath('error.code', 'admin.access_code_invalid');
});

it('sets a new password with the access code and lets the user log in', function (): void {
    $user = User::factory()->create(['email' => 'returning@example.com']);

    $this->postJson("/api/v1/admin/users/{$user->id}/password", [
        'password' => 'brandnew-pass-1',
        'access_code' => '023050',
    ], idem())->assertOk();

    // Old password no longer works is implied; new one authenticates.
    $this->postJson('/api/v1/auth/sessions', [
        'email' => 'returning@example.com',
        'password' => 'brandnew-pass-1',
    ], idem())->assertOk()->assertJsonStructure(['data' => ['token']]);
});

it('force-verifies an email only with the access code', function (): void {
    $user = User::factory()->unverified()->create(['email' => 'pending@example.com']);

    $this->postJson("/api/v1/admin/users/{$user->id}/force-verify", [
        'channel' => 'email', 'access_code' => '023050',
    ], idem())->assertOk()->assertJsonPath('data.email_verified', true);

    expect($user->refresh()->email_verified_at)->not->toBeNull();
});

it('disables an account and blocks its login, then re-enables', function (): void {
    $user = User::factory()->create([
        'email' => 'live@example.com',
        'password' => Hash::make('secret-pass-1'),
        'claimed_at' => now(),
    ]);

    $this->postJson("/api/v1/admin/users/{$user->id}/disable", [], idem())->assertOk()
        ->assertJsonPath('data.status', 'disabled');

    $this->postJson('/api/v1/auth/sessions', [
        'email' => 'live@example.com', 'password' => 'secret-pass-1',
    ], idem())->assertStatus(422);

    $this->postJson("/api/v1/admin/users/{$user->id}/enable", [], idem())->assertOk()
        ->assertJsonPath('data.status', 'active');

    $this->postJson('/api/v1/auth/sessions', [
        'email' => 'live@example.com', 'password' => 'secret-pass-1',
    ], idem())->assertOk();
});

it('resends an OTP only when the number matches the account', function (): void {
    $user = User::factory()->withPhone('+233244123456')->create();

    $this->postJson("/api/v1/admin/users/{$user->id}/resend-otp", [
        'phone_e164' => '+233244999999',
    ], idem())->assertStatus(422)->assertJsonPath('error.code', 'admin.users.phone_mismatch');

    $this->postJson("/api/v1/admin/users/{$user->id}/resend-otp", [
        'phone_e164' => '+233244123456',
    ], idem())->assertOk();

    expect($this->mockOtp->lastSent())->not->toBeNull();
});

it('audits a destructive action with the access code redacted', function (): void {
    $user = User::factory()->create();

    $this->postJson("/api/v1/admin/users/{$user->id}/password", [
        'password' => 'sup3r-secret-pw', 'access_code' => '023050',
    ], idem())->assertOk();

    $row = DB::table('admin_audit_log')->where('path', 'like', "%/users/{$user->id}/password")->first();
    expect($row)->not->toBeNull();
    expect($row->payload_redacted)->not->toContain('sup3r-secret-pw');
    expect($row->payload_redacted)->not->toContain('023050');
});

it('archives (soft-deletes) a user only with the access code', function (): void {
    $user = User::factory()->withPhone('+233244555666')->create(['email' => 'gone@example.com']);

    $this->postJson("/api/v1/admin/users/{$user->id}/archive", [], idem())
        ->assertStatus(403)->assertJsonPath('error.code', 'admin.access_code_invalid');

    $this->postJson("/api/v1/admin/users/{$user->id}/archive", ['access_code' => '023050'], idem())
        ->assertOk()->assertJsonPath('data.status', 'archived');

    expect($user->refresh()->archived_at)->not->toBeNull();
});

it('refuses to archive an admin account (no hard delete, Law 13)', function (): void {
    $admin = User::factory()->withRole(Role::SuperAdmin)->create();

    $this->postJson("/api/v1/admin/users/{$admin->id}/archive", ['access_code' => '023050'], idem())
        ->assertStatus(422)->assertJsonPath('error.code', 'admin.users.cannot_archive_admin');

    expect($admin->refresh()->archived_at)->toBeNull();
});
