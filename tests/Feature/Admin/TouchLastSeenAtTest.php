<?php

declare(strict_types=1);

use App\Http\Middleware\TouchLastSeenAt;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Kalaanba\Support\Auth\Role;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Cache::flush();
    Carbon::setTestNow();
});

it('updates last_seen_at for an authenticated request', function (): void {
    $user = User::factory()->create(['role' => Role::Fan, 'last_seen_at' => null]);

    $request = Request::create('/api/whatever', 'GET');
    $request->setUserResolver(fn () => $user);

    (new TouchLastSeenAt)->handle($request, fn () => new Response('ok', 200));

    $user->refresh();
    expect($user->last_seen_at)->not->toBeNull();
});

it('throttles writes via the cache layer', function (): void {
    $user = User::factory()->create(['role' => Role::Fan, 'last_seen_at' => null]);

    $request = Request::create('/api/whatever', 'GET');
    $request->setUserResolver(fn () => $user);

    Carbon::setTestNow('2026-05-25T10:00:00Z');
    (new TouchLastSeenAt)->handle($request, fn () => new Response('ok', 200));
    $user->refresh();
    $first = $user->last_seen_at;
    expect($first)->not->toBeNull();

    // Second call 5 seconds later — same minute, should NOT update the row.
    Carbon::setTestNow('2026-05-25T10:00:05Z');
    (new TouchLastSeenAt)->handle($request, fn () => new Response('ok', 200));
    $user->refresh();
    expect($user->last_seen_at?->equalTo($first))->toBeTrue();
});

it('skips when no user is on the request', function (): void {
    $request = Request::create('/api/whatever', 'GET');
    $response = (new TouchLastSeenAt)->handle($request, fn () => new Response('ok', 200));
    expect($response->getStatusCode())->toBe(200);
});
