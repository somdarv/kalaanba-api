<?php

declare(strict_types=1);

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Kalaanba\Support\Http\Middleware\RequestIdMiddleware;

it('echoes a supplied X-Request-Id header back on the response', function (): void {
    $middleware = new RequestIdMiddleware;

    $request = Request::create('/whatever', 'POST');
    $request->headers->set('X-Request-Id', 'req-abc-123');

    $response = $middleware->handle($request, fn (): Response => new Response('ok'));

    expect($response->headers->get('X-Request-Id'))->toBe('req-abc-123')
        ->and($request->attributes->get('request_id'))->toBe('req-abc-123');
});

it('generates a UUID when no X-Request-Id is supplied', function (): void {
    $middleware = new RequestIdMiddleware;

    $request = Request::create('/whatever', 'GET');

    $response = $middleware->handle($request, fn (): Response => new Response('ok'));

    $id = $response->headers->get('X-Request-Id');

    expect($id)->toBeString()
        ->and($id)->toMatch('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/');
});

it('rejects and regenerates an oversized X-Request-Id header', function (): void {
    $middleware = new RequestIdMiddleware;

    $request = Request::create('/whatever', 'GET');
    $request->headers->set('X-Request-Id', str_repeat('a', 200));

    $response = $middleware->handle($request, fn (): Response => new Response('ok'));

    $id = $response->headers->get('X-Request-Id');

    expect(strlen((string) $id))->toBeLessThanOrEqual(64)
        ->and($id)->not->toBe(str_repeat('a', 200));
});

it('shares the request id into the log context', function (): void {
    $middleware = new RequestIdMiddleware;

    $request = Request::create('/whatever', 'GET');
    $request->headers->set('X-Request-Id', 'log-corr-99');

    $middleware->handle($request, fn (): Response => new Response('ok'));

    // Log::shareContext merges into subsequent log records; assert by writing one.
    $captured = null;
    Log::listen(function ($message) use (&$captured): void {
        $captured = $message;
    });

    Log::info('observability-lite test');

    expect($captured?->context['request_id'] ?? null)->toBe('log-corr-99');
});
