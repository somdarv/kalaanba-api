<?php

declare(strict_types=1);

use App\Models\User;
use Database\Seeders\ZoneHierarchySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Kalaanba\Modules\Zone\Application\SubmitAreaSuggestion;
use Kalaanba\Support\Auth\Role;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(ZoneHierarchySeeder::class);

    $this->cityHubId = '8c2f9d0a-2c5b-4e3e-9c1e-6a3b1a0e1003';
    $this->zoneId = '8c2f9d0a-2c5b-4e3e-9c1e-6a3b1a0e1004';
    $this->userId = '00000000-0000-0000-0000-000000000aaa';

    $this->submit = $this->app->make(SubmitAreaSuggestion::class);
});

it('rejects unauthenticated callers with 401', function (): void {
    $this->getJson('/api/v1/admin/zone/area-suggestions')->assertStatus(401);
});

it('rejects authenticated non-super-admins with auth.super_admin_only', function (): void {
    $hub = User::factory()->withRole(Role::HubAdmin)->create();
    Sanctum::actingAs($hub, ['*']);

    $this->getJson('/api/v1/admin/zone/area-suggestions')
        ->assertStatus(403)
        ->assertJsonPath('error.code', 'auth.super_admin_only');
});

it('lists suggestions newest-first for a Super Admin', function (): void {
    $super = User::factory()->withRole(Role::SuperAdmin)->create();
    Sanctum::actingAs($super, ['*']);

    $this->submit->execute(
        cityHubId: $this->cityHubId,
        proposedZoneId: $this->zoneId,
        proposedName: 'Sakasaka',
        note: null,
        submittedByUserId: $this->userId,
    );

    $this->getJson('/api/v1/admin/zone/area-suggestions')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.proposed_name', 'Sakasaka')
        ->assertJsonPath('data.0.status', 'pending');
});

it('filters by status', function (): void {
    $super = User::factory()->withRole(Role::SuperAdmin)->create();
    Sanctum::actingAs($super, ['*']);

    $this->submit->execute($this->cityHubId, $this->zoneId, 'Choggu', null, $this->userId);

    $this->getJson('/api/v1/admin/zone/area-suggestions?status=approved')
        ->assertOk()
        ->assertJsonCount(0, 'data');

    $this->getJson('/api/v1/admin/zone/area-suggestions?status=pending')
        ->assertOk()
        ->assertJsonCount(1, 'data');
});

it('approve creates an area and returns the updated suggestion', function (): void {
    $super = User::factory()->withRole(Role::SuperAdmin)->create();
    Sanctum::actingAs($super, ['*']);

    $suggestion = $this->submit->execute(
        cityHubId: $this->cityHubId,
        proposedZoneId: $this->zoneId,
        proposedName: 'Choggu',
        note: null,
        submittedByUserId: $this->userId,
    );

    // Sanity: row must exist before the HTTP call.
    expect(DB::table('area_suggestions')->where('id', $suggestion->id)->count())->toBe(1);

    $response = $this->postJson("/api/v1/admin/zone/area-suggestions/{$suggestion->id}/approve", [
        'review_note' => 'Looks good',
    ], ['Idempotency-Key' => 'approve-choggu-1']);

    $response
        ->assertOk()
        ->assertJsonPath('data.status', 'approved')
        ->assertJsonPath('data.review_note', 'Looks good');

    expect(DB::table('areas')->where('zone_id', $this->zoneId)->where('name', 'Choggu')->count())->toBe(1);
});

it('approve is idempotent at the HTTP layer', function (): void {
    $super = User::factory()->withRole(Role::SuperAdmin)->create();
    Sanctum::actingAs($super, ['*']);

    $suggestion = $this->submit->execute($this->cityHubId, $this->zoneId, 'Vittin', null, $this->userId);

    $this->postJson("/api/v1/admin/zone/area-suggestions/{$suggestion->id}/approve", [], ['Idempotency-Key' => 'approve-vittin-1'])->assertOk();
    $this->postJson("/api/v1/admin/zone/area-suggestions/{$suggestion->id}/approve", [], ['Idempotency-Key' => 'approve-vittin-2'])->assertOk();

    expect(DB::table('areas')->where('zone_id', $this->zoneId)->where('name', 'Vittin')->count())->toBe(1);
    expect(DB::table('outbox_events')->where('event_name', 'zone.area_approved')->count())->toBe(1);
});

it('reject marks the suggestion rejected and preserves the row', function (): void {
    $super = User::factory()->withRole(Role::SuperAdmin)->create();
    Sanctum::actingAs($super, ['*']);

    $suggestion = $this->submit->execute($this->cityHubId, $this->zoneId, 'Bogus', null, $this->userId);

    $this->postJson("/api/v1/admin/zone/area-suggestions/{$suggestion->id}/reject", [
        'review_note' => 'Duplicate',
    ], ['Idempotency-Key' => 'reject-bogus-1'])
        ->assertOk()
        ->assertJsonPath('data.status', 'rejected')
        ->assertJsonPath('data.review_note', 'Duplicate');

    expect(DB::table('area_suggestions')->where('id', $suggestion->id)->count())->toBe(1);
});

it('returns 404 for unknown suggestion id on approve', function (): void {
    $super = User::factory()->withRole(Role::SuperAdmin)->create();
    Sanctum::actingAs($super, ['*']);

    $this->postJson('/api/v1/admin/zone/area-suggestions/00000000-0000-0000-0000-00000000dead/approve', [], ['Idempotency-Key' => 'approve-missing-1'])
        ->assertStatus(404)
        ->assertJsonPath('error.code', 'zone.suggestion_not_found');
});
