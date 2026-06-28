<?php

declare(strict_types=1);

use App\Models\User;
use Database\Seeders\ZoneHierarchySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(ZoneHierarchySeeder::class);
    $this->cityHubId = '8c2f9d0a-2c5b-4e3e-9c1e-6a3b1a0e1003';
});

// ─── GET /zone/hubs ──────────────────────────────────────────────────

it('lists city hubs with their region for the picker (no auth)', function (): void {
    $this->getJson('/api/v1/zone/hubs')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $this->cityHubId)
        ->assertJsonPath('data.0.name', 'Tamale')
        ->assertJsonPath('data.0.region', 'Northern Region');
});

// ─── GET /zone/areas ─────────────────────────────────────────────────

it('lists areas in a hub (no auth) and never leaks zone mapping', function (): void {
    $response = $this->getJson("/api/v1/zone/areas?city_hub_id={$this->cityHubId}")
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.name', 'Aboabo')
        ->assertJsonPath('data.0.city_hub_id', $this->cityHubId);

    expect($response->json('data.0'))->not->toHaveKey('zone_id');
});

it('filters areas by case-insensitive q', function (): void {
    $this->getJson("/api/v1/zone/areas?city_hub_id={$this->cityHubId}&q=abo")
        ->assertOk()
        ->assertJsonCount(1, 'data');

    $this->getJson("/api/v1/zone/areas?city_hub_id={$this->cityHubId}&q=zzz")
        ->assertOk()
        ->assertJsonCount(0, 'data');
});

it('requires city_hub_id and rejects an unknown hub', function (): void {
    $this->getJson('/api/v1/zone/areas')
        ->assertStatus(422)
        ->assertJsonPath('error.code', 'zone.city_hub_id_required');

    $this->getJson('/api/v1/zone/areas?city_hub_id=00000000-0000-0000-0000-0000000000ff')
        ->assertStatus(422)
        ->assertJsonPath('error.code', 'zone.city_hub_not_found');
});

// ─── POST /zone/area-suggestions ─────────────────────────────────────

it('rejects an unauthenticated suggestion with 401', function (): void {
    $this->postJson('/api/v1/zone/area-suggestions', [
        'city_hub_id' => $this->cityHubId,
        'proposed_name' => 'Sakasaka',
    ], ['Idempotency-Key' => 'suggest-anon-1'])->assertStatus(401);
});

it('accepts a suggestion from a signed-in user and queues it as pending', function (): void {
    Sanctum::actingAs(User::factory()->create(), ['*']);

    $this->postJson('/api/v1/zone/area-suggestions', [
        'city_hub_id' => $this->cityHubId,
        'proposed_name' => 'Sakasaka',
        'note' => 'Football-active community',
    ], ['Idempotency-Key' => 'suggest-saka-1'])
        ->assertStatus(201)
        ->assertJsonPath('data.status', 'pending');

    expect(DB::table('area_suggestions')->where('proposed_name', 'Sakasaka')->count())->toBe(1);
});

it('rejects a suggestion for an unknown hub with 422', function (): void {
    Sanctum::actingAs(User::factory()->create(), ['*']);

    $this->postJson('/api/v1/zone/area-suggestions', [
        'city_hub_id' => '00000000-0000-0000-0000-0000000000ff',
        'proposed_name' => 'Nowhere',
    ], ['Idempotency-Key' => 'suggest-bad-hub-1'])
        ->assertStatus(422)
        ->assertJsonPath('error.code', 'zone.city_hub_not_found');
});

it('validates the proposed name length', function (): void {
    Sanctum::actingAs(User::factory()->create(), ['*']);

    $this->postJson('/api/v1/zone/area-suggestions', [
        'city_hub_id' => $this->cityHubId,
        'proposed_name' => 'a',
    ], ['Idempotency-Key' => 'suggest-short-1'])
        ->assertStatus(422)
        ->assertJsonValidationErrors('proposed_name');
});

// ─── API exception shape (the unauth-500 fix) ────────────────────────

it('returns JSON 401 for a protected API route even without an Accept header', function (): void {
    // No `Accept: application/json` — previously this fell into the web
    // Authenticate redirect and 500'd with `Route [login] not defined`.
    $this->get('/api/v1/users/me')
        ->assertStatus(401)
        ->assertHeader('Content-Type', 'application/json');
});
