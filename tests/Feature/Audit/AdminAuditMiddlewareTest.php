<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Kalaanba\Support\Auth\Role;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

it('logs an audit entry when a platform admin performs an authenticated write', function (): void {
    $admin = User::factory()->withRole(Role::SuperAdmin)->create();

    // Use the existing logout endpoint as a generic authenticated DELETE.
    Sanctum::actingAs($admin, ['*']);

    $this->withHeaders(['Idempotency-Key' => 'audit-test-1'])
        ->deleteJson('/api/v1/auth/sessions/current')
        ->assertSuccessful();

    $rows = DB::table('admin_audit_log')->get();
    expect($rows)->toHaveCount(1);

    $row = $rows->first();
    expect($row->actor_id)->toBe((string) $admin->getKey())
        ->and($row->actor_role)->toBe('super_admin')
        ->and($row->method)->toBe('DELETE')
        ->and($row->path)->toBe('api/v1/auth/sessions/current');
});

it('does NOT log audit entries for non-admin actors', function (): void {
    $fan = User::factory()->withRole(Role::Fan)->create();
    Sanctum::actingAs($fan, ['*']);

    $this->withHeaders(['Idempotency-Key' => 'audit-test-2'])
        ->deleteJson('/api/v1/auth/sessions/current')
        ->assertSuccessful();

    expect(DB::table('admin_audit_log')->count())->toBe(0);
});

it('does NOT log audit entries for safe methods (GET/HEAD/OPTIONS)', function (): void {
    $admin = User::factory()->withRole(Role::SuperAdmin)->create();
    Sanctum::actingAs($admin, ['*']);

    // Hit any GET endpoint — the audit-log reader itself is fine.
    $this->getJson('/api/v1/admin/audit-log')->assertOk();

    expect(DB::table('admin_audit_log')->count())->toBe(0);
});

it('redacts secrets / OTPs / phone numbers from the persisted payload', function (): void {
    $admin = User::factory()->withRole(Role::HubAdmin)->create();
    Sanctum::actingAs($admin, ['*']);

    // OTP verify endpoint takes phone_e164 + otp — both are sensitive.
    // The endpoint will 422 because no OTP was issued, but the middleware
    // still records the attempt.
    $this->withHeaders(['Idempotency-Key' => 'audit-test-3'])
        ->postJson('/api/v1/auth/otp/verify', [
            'phone_e164' => '+233244999999',
            'otp' => '123456',
            'device_name' => 'pest',
        ]);

    $row = DB::table('admin_audit_log')->first();
    expect($row)->not->toBeNull();
    $payload = json_decode((string) $row->payload_redacted, true);

    expect($payload['phone_e164'])->toBe('[redacted]')
        ->and($payload['otp'])->toBe('[redacted]')
        ->and($payload['device_name'])->toBe('pest');
});
