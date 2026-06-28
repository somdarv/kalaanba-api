<?php

use App\Http\Middleware\TouchLastSeenAt;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Kalaanba\Support\Http\Middleware\AdminAuditMiddleware;
use Kalaanba\Support\Http\Middleware\IdempotencyKeyMiddleware;
use Kalaanba\Support\Http\Middleware\RequestIdMiddleware;
use Kalaanba\Support\Http\Middleware\RequireSuperAdminMiddleware;
use Kalaanba\Support\Http\Middleware\ScopeMiddleware;
use Sentry\Laravel\Integration;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withCommands([
        __DIR__.'/../app/Console/Commands',
    ])
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'idempotency' => IdempotencyKeyMiddleware::class,
            'scope' => ScopeMiddleware::class,
            'super_admin' => RequireSuperAdminMiddleware::class,
        ]);

        // Request-id MUST run first on the api group so every downstream
        // middleware, log line, audit row, and Sentry event carries the
        // same correlation id (Phase 0.8 — Observability Lite).
        $middleware->prependToGroup('api', [
            RequestIdMiddleware::class,
        ]);

        // Auto-audit every authenticated mutating call by a platform admin.
        // Engines never write to admin_audit_log directly (Constitution Law 5).
        $middleware->appendToGroup('api', [
            AdminAuditMiddleware::class,
            TouchLastSeenAt::class,
        ]);

        // Guests hitting an /api/* route must NOT be redirected to a (web)
        // `login` route — there is none, so the default callback 500s with
        // `Route [login] not defined`. Returning null lets the Authentication
        // exception surface and be rendered as JSON 401 (see withExceptions).
        $middleware->redirectGuestsTo(
            static fn (Request $request): ?string => $request->is('api/*') ? null : '/login',
        );
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Wires Sentry's exception reporter. No-op when SENTRY_LARAVEL_DSN
        // is empty (sentry-laravel handles that gracefully).
        Integration::handles($exceptions);

        // API is JSON-only: render every exception as JSON for /api/* even when
        // the client omits `Accept: application/json`. Without this, an
        // unauthenticated API call falls into the web Authenticate redirect and
        // 500s with `Route [login] not defined` instead of a clean 401.
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request, Throwable $e): bool => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
