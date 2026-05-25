<?php

declare(strict_types=1);

use App\Http\Middleware\AdminAuditLogger;
use App\Models\Admin\AdminAuditLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Str;
use Kalaanba\Support\Auth\Role;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    AdminAuditLog::query()->delete();
    User::query()->where('role', Role::SuperAdmin->value)->delete();
});

it('does not write an audit row for GETs', function (): void {
    $admin = User::factory()->create(['role' => Role::SuperAdmin, 'archived_at' => null]);

    $this->actingAs($admin)->get('/admin/users')->assertOk();

    expect(AdminAuditLog::query()->count())->toBe(0);
});

it('writes an audit row for a mutating request and redacts secrets', function (): void {
    $admin = User::factory()->create(['role' => Role::SuperAdmin, 'archived_at' => null]);

    $request = Request::create('/admin/users', 'POST', [
        'password' => 'should-not-appear',
        'token' => 'should-not-appear',
        'extra' => 'visible-value',
        'nested' => ['secret' => 'shh', 'name' => 'ok'],
    ]);
    $request->setUserResolver(fn () => $admin);
    $request->headers->set('X-Request-Id', (string) Str::uuid());

    $middleware = new AdminAuditLogger;
    $response = $middleware->handle($request, fn () => new Response('ok', 200));

    expect($response->getStatusCode())->toBe(200);
    expect(AdminAuditLog::query()->count())->toBe(1);

    $row = AdminAuditLog::query()->first();
    expect($row)->not->toBeNull();
    expect($row->actor_id)->toBe((string) $admin->getAuthIdentifier());
    expect($row->actor_role)->toBe(Role::SuperAdmin->value);
    expect($row->method)->toBe('POST');
    expect($row->response_status)->toBe(200);

    $payload = $row->payload_redacted;
    expect($payload['password'] ?? null)->toBe('[redacted]');
    expect($payload['token'] ?? null)->toBe('[redacted]');
    expect($payload['extra'] ?? null)->toBe('visible-value');
    expect($payload['nested']['secret'] ?? null)->toBe('[redacted]');
    expect($payload['nested']['name'] ?? null)->toBe('ok');
});

it('skips logging when no authenticated user is on the request', function (): void {
    $request = Request::create('/admin/users', 'POST', ['x' => 1]);
    $middleware = new AdminAuditLogger;
    $middleware->handle($request, fn () => new Response('ok', 200));

    expect(AdminAuditLog::query()->count())->toBe(0);
});
