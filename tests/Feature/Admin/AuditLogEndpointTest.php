<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Kalaanba\Support\Auth\Role;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

function seedAuditRow(string $occurredAt, string $id): void
{
    DB::table('admin_audit_log')->insert([
        'id' => $id,
        'actor_id' => '00000000-0000-0000-0000-000000000001',
        'actor_role' => 'super_admin',
        'request_id' => 'req-'.$id,
        'route' => 'admin.test',
        'method' => 'POST',
        'path' => 'api/v1/admin/test',
        'response_status' => 200,
        'payload_redacted' => json_encode(['ok' => true]),
        'occurred_at' => $occurredAt,
    ]);
}

it('rejects unauthenticated callers with 401', function (): void {
    $this->getJson('/api/v1/admin/audit-log')->assertStatus(401);
});

it('rejects authenticated non-super-admins with auth.super_admin_only', function (): void {
    $hubAdmin = User::factory()->withRole(Role::HubAdmin)->create();
    Sanctum::actingAs($hubAdmin, ['*']);

    $this->getJson('/api/v1/admin/audit-log')
        ->assertStatus(403)
        ->assertJsonPath('error.code', 'auth.super_admin_only');
});

it('returns newest-first cursor-paginated entries for a Super Admin', function (): void {
    $super = User::factory()->withRole(Role::SuperAdmin)->create();
    Sanctum::actingAs($super, ['*']);

    seedAuditRow('2026-05-22 10:00:00+00', '11111111-1111-1111-1111-111111111111');
    seedAuditRow('2026-05-22 11:00:00+00', '22222222-2222-2222-2222-222222222222');
    seedAuditRow('2026-05-22 12:00:00+00', '33333333-3333-3333-3333-333333333333');

    $response = $this->getJson('/api/v1/admin/audit-log?limit=2');
    $response->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.id', '33333333-3333-3333-3333-333333333333')
        ->assertJsonPath('data.1.id', '22222222-2222-2222-2222-222222222222')
        ->assertJsonPath('meta.limit', 2);

    $nextCursor = $response->json('meta.next_cursor');
    expect($nextCursor)->not->toBeNull();

    $page2 = $this->getJson('/api/v1/admin/audit-log?limit=2&cursor='.$nextCursor);
    $page2->assertOk()
        ->assertJsonPath('data.0.id', '11111111-1111-1111-1111-111111111111')
        ->assertJsonPath('meta.next_cursor', null);
});

it('caps limit at 100', function (): void {
    $super = User::factory()->withRole(Role::SuperAdmin)->create();
    Sanctum::actingAs($super, ['*']);

    $this->getJson('/api/v1/admin/audit-log?limit=9999')
        ->assertOk()
        ->assertJsonPath('meta.limit', 100);
});
