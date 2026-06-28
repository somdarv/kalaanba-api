<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    RateLimiter::clear('lookup');
});

function lookup(array $body): \Illuminate\Testing\TestResponse
{
    return test()->withHeaders(['Accept' => 'application/json'])
        ->postJson('/api/v1/auth/lookup', $body);
}

it('reports exists=true for a known active phone', function (): void {
    User::factory()->withPhone('+233244123456')->create();

    lookup(['identifier' => '+233244123456'])
        ->assertOk()
        ->assertExactJson(['data' => ['exists' => true, 'channel' => 'phone']]);
});

it('reports exists=false for an unknown phone', function (): void {
    lookup(['identifier' => '+233244999999'])
        ->assertOk()
        ->assertExactJson(['data' => ['exists' => false, 'channel' => 'phone']]);
});

it('reports exists=true for a known active email (case-insensitive)', function (): void {
    User::factory()->create(['email' => 'kojo@example.com']);

    lookup(['identifier' => 'KOJO@example.com'])
        ->assertOk()
        ->assertExactJson(['data' => ['exists' => true, 'channel' => 'email']]);
});

it('reports exists=false for an unknown email', function (): void {
    lookup(['identifier' => 'nobody@example.com'])
        ->assertOk()
        ->assertExactJson(['data' => ['exists' => false, 'channel' => 'email']]);
});

it('treats an archived account as exists=false (identifier re-registerable)', function (): void {
    User::factory()->archived()->withPhone('+233244123456')->create();

    lookup(['identifier' => '+233244123456'])
        ->assertOk()
        ->assertJsonPath('data.exists', false);
});

it('never leaks PII — response carries only exists + channel', function (): void {
    User::factory()->withPhone('+233244123456')->create(['name' => 'Secret Name']);

    $response = lookup(['identifier' => '+233244123456'])->assertOk();

    $data = $response->json('data');
    expect(array_keys($data))->toEqualCanonicalizing(['exists', 'channel']);
    $response->assertDontSee('Secret Name');
});

it('rejects an identifier that is neither phone nor email', function (): void {
    lookup(['identifier' => 'not-an-identifier'])
        ->assertStatus(422)
        ->assertJsonPath('errors.identifier.0', 'auth.identifier_invalid');
});

it('requires no Idempotency-Key (read-only)', function (): void {
    // No Idempotency-Key header supplied — must still succeed.
    lookup(['identifier' => '+233244123456'])->assertOk();
});

it('rate-limits lookups per identifier+ip', function (): void {
    for ($i = 0; $i < 5; $i++) {
        lookup(['identifier' => '+233244111111'])->assertOk();
    }

    lookup(['identifier' => '+233244111111'])->assertStatus(429);
});
