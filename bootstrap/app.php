<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Kalaanba\Support\Http\Middleware\AdminAuditMiddleware;
use Kalaanba\Support\Http\Middleware\IdempotencyKeyMiddleware;
use Kalaanba\Support\Http\Middleware\RequireSuperAdminMiddleware;
use Kalaanba\Support\Http\Middleware\ScopeMiddleware;

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

        // Auto-audit every authenticated mutating call by a platform admin.
        // Engines never write to admin_audit_log directly (Constitution Law 5).
        $middleware->appendToGroup('api', [
            AdminAuditMiddleware::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
