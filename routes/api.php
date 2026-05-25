<?php

declare(strict_types=1);

use App\Http\Controllers\Admin\AuditLogController;
use App\Http\Controllers\Auth\OtpController;
use App\Http\Controllers\Auth\SessionController;
use App\Http\Controllers\Notifications\MyInboxController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes — v1
|--------------------------------------------------------------------------
|
| Every route lives under /api/v1/. Breaking changes go to /api/v2/.
| See engineering-standards §7 and contracts/api/.
|
*/

Route::prefix('v1')->group(function (): void {
    Route::prefix('auth')->group(function (): void {
        Route::middleware(['throttle:auth', 'idempotency'])
            ->post('sessions', [SessionController::class, 'store'])
            ->name('auth.sessions.store');

        Route::middleware(['auth:sanctum', 'idempotency'])
            ->delete('sessions/current', [SessionController::class, 'destroyCurrent'])
            ->name('auth.sessions.destroy-current');

        Route::middleware(['throttle:otp', 'idempotency'])
            ->post('otp/request', [OtpController::class, 'request'])
            ->name('auth.otp.request');

        Route::middleware(['throttle:otp', 'idempotency'])
            ->post('otp/verify', [OtpController::class, 'verify'])
            ->name('auth.otp.verify');
    });

    Route::prefix('admin')->middleware(['auth:sanctum', 'super_admin'])->group(function (): void {
        Route::get('audit-log', [AuditLogController::class, 'index'])
            ->name('admin.audit-log.index');
    });

    Route::prefix('me/notifications')->middleware(['auth:sanctum'])->group(function (): void {
        Route::get('/', [MyInboxController::class, 'index'])
            ->name('me.notifications.index');

        Route::get('unread-count', [MyInboxController::class, 'unreadCount'])
            ->name('me.notifications.unread-count');

        Route::middleware('idempotency')
            ->post('{id}/seen', [MyInboxController::class, 'markSeen'])
            ->whereUuid('id')
            ->name('me.notifications.seen');

        Route::middleware('idempotency')
            ->post('{id}/acted-on', [MyInboxController::class, 'markActedOn'])
            ->whereUuid('id')
            ->name('me.notifications.acted-on');
    });
});
