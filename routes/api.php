<?php

declare(strict_types=1);

use App\Http\Controllers\Admin\AuditLogController;
use App\Http\Controllers\Admin\ConfigController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Admin\Zone\AreaSuggestionController as AdminZoneAreaSuggestionController;
use App\Http\Controllers\Auth\EmailVerificationController;
use App\Http\Controllers\Auth\LookupController;
use App\Http\Controllers\Auth\OtpController;
use App\Http\Controllers\Auth\RegistrationController;
use App\Http\Controllers\Auth\SessionController;
use App\Http\Controllers\Club\AffiliationController;
use App\Http\Controllers\Club\ClubController;
use App\Http\Controllers\HealthController;
use App\Http\Controllers\Identity\AvatarController;
use App\Http\Controllers\Identity\ChannelBindingController;
use App\Http\Controllers\Identity\MeController;
use App\Http\Controllers\Identity\UserShowController;
use App\Http\Controllers\Notifications\MyInboxController;
use App\Http\Controllers\Player\PlayerController;
use App\Http\Controllers\Player\PlayerMetaController;
use App\Http\Controllers\Zone\AreaSuggestionController as ZoneAreaSuggestionController;
use App\Http\Controllers\Zone\GeographyController as ZoneGeographyController;
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
    // Liveness + dependency probe. Phase 0.8 — Observability Lite.
    // Unauthenticated by design; surfaces DB + Redis reachability.
    Route::get('health', HealthController::class)->name('health');

    Route::prefix('auth')->group(function (): void {
        // WP-20260624 — identifier-first branch signal (ADR-0004).
        // Read-only: no Idempotency-Key (not a write). Strictly throttled.
        Route::middleware('throttle:lookup')
            ->post('lookup', [LookupController::class, 'store'])
            ->name('auth.lookup');

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

        // WP-20260530 — self-signup + email-verify.
        Route::middleware(['throttle:registration', 'idempotency'])
            ->post('registration', [RegistrationController::class, 'store'])
            ->name('auth.registration.store');

        Route::middleware(['throttle:email-verify', 'idempotency'])
            ->post('email/verify', [EmailVerificationController::class, 'store'])
            ->name('auth.email.verify');
    });

    Route::prefix('admin')->middleware(['auth:sanctum', 'super_admin'])->group(function (): void {
        Route::get('audit-log', [AuditLogController::class, 'index'])
            ->name('admin.audit-log.index');

        Route::get('configs', [ConfigController::class, 'index'])
            ->name('admin.configs.index');

        // WP-20260624 — Users section (pre-alpha tester support). Reads are
        // open to any super admin; destructive actions (password, force-verify)
        // additionally require the admin access code, checked in the controller.
        Route::prefix('users')->group(function (): void {
            Route::get('/', [UserController::class, 'index'])->name('admin.users.index');
            Route::get('{id}', [UserController::class, 'show'])
                ->whereUuid('id')->name('admin.users.show');

            Route::middleware('idempotency')->group(function (): void {
                Route::post('{id}/password', [UserController::class, 'setPassword'])->whereUuid('id');
                Route::post('{id}/force-verify', [UserController::class, 'forceVerify'])->whereUuid('id');
                Route::patch('{id}/phone', [UserController::class, 'updatePhone'])->whereUuid('id');
                Route::patch('{id}/email', [UserController::class, 'updateEmail'])->whereUuid('id');
                Route::post('{id}/disable', [UserController::class, 'disable'])->whereUuid('id');
                Route::post('{id}/enable', [UserController::class, 'enable'])->whereUuid('id');
                Route::post('{id}/archive', [UserController::class, 'archive'])->whereUuid('id');
                Route::post('{id}/resend-otp', [UserController::class, 'resendOtp'])->whereUuid('id');
                Route::post('{id}/clear-lockout', [UserController::class, 'clearLockout'])->whereUuid('id');
            });
        });

        Route::prefix('zone')->group(function (): void {
            Route::get('area-suggestions', [AdminZoneAreaSuggestionController::class, 'index'])
                ->name('admin.zone.area-suggestions.index');

            Route::middleware('idempotency')
                ->post('area-suggestions/{id}/approve', [AdminZoneAreaSuggestionController::class, 'approve'])
                ->whereUuid('id')
                ->name('admin.zone.area-suggestions.approve');

            Route::middleware('idempotency')
                ->post('area-suggestions/{id}/reject', [AdminZoneAreaSuggestionController::class, 'rejectSuggestion'])
                ->whereUuid('id')
                ->name('admin.zone.area-suggestions.reject');
        });
    });

    // Zone engine — public geography reads + user-facing area suggestion.
    // Engine doc: docs/engines/zone/Zone_Engine_UPDATED.md §2, §5.
    Route::prefix('zone')->group(function (): void {
        Route::middleware('throttle:zone-read')->group(function (): void {
            Route::get('hubs', [ZoneGeographyController::class, 'hubs'])
                ->name('zone.hubs');
            Route::get('areas', [ZoneGeographyController::class, 'areas'])
                ->name('zone.areas');
        });

        Route::middleware(['auth:sanctum', 'throttle:zone-suggest', 'idempotency'])
            ->post('area-suggestions', [ZoneAreaSuggestionController::class, 'store'])
            ->name('zone.area-suggestions.store');
    });

    // Player & Affiliation engine — self-service player-profile creation.
    // Engine doc: docs/engines/player-affiliation/ §4, §22. WP-20260702.
    Route::prefix('players')->group(function (): void {
        // Profile-form vocabulary (ADR-0007). Public reference data — no
        // player, no user, nothing computed; cached at the edge.
        Route::middleware('throttle:player-read')
            ->get('meta', [PlayerMetaController::class, 'show'])
            ->name('players.meta');

        Route::middleware(['auth:sanctum', 'throttle:player-create', 'idempotency'])
            ->post('/', [PlayerController::class, 'store'])
            ->name('players.store');
    });

    // Club engine — create a club + "clubs near you" discovery.
    // Engine doc: docs/engines/club/ §5, §6, §15. WP-20260702 (WP-C1).
    Route::prefix('clubs')->middleware('auth:sanctum')->group(function (): void {
        Route::middleware('throttle:club-read')
            ->get('/', [ClubController::class, 'index'])
            ->name('clubs.index');

        // Clubs the caller administers (for the join-request accept surface).
        Route::middleware('throttle:club-read')
            ->get('mine', [ClubController::class, 'mine'])
            ->name('clubs.mine');

        Route::middleware(['throttle:club-create', 'idempotency'])
            ->post('/', [ClubController::class, 'store'])
            ->name('clubs.store');

        // Affiliation join lifecycle (Player & Affiliation §8/§11). WP-C2.
        Route::prefix('{clubId}/join-requests')->whereUuid('clubId')->group(function (): void {
            Route::middleware('throttle:club-read')
                ->get('/', [AffiliationController::class, 'index'])
                ->name('clubs.join-requests.index');

            Route::middleware(['throttle:affiliation-join', 'idempotency'])
                ->post('/', [AffiliationController::class, 'store'])
                ->name('clubs.join-requests.store');

            Route::middleware(['throttle:affiliation-join', 'idempotency'])
                ->post('{affiliationId}/accept', [AffiliationController::class, 'accept'])
                ->whereUuid('affiliationId')
                ->name('clubs.join-requests.accept');

            Route::middleware(['throttle:affiliation-join', 'idempotency'])
                ->post('{affiliationId}/decline', [AffiliationController::class, 'decline'])
                ->whereUuid('affiliationId')
                ->name('clubs.join-requests.decline');
        });
    });

    // Identity engine — self profile + public projection.
    // Engine doc: docs/engines/identity/Identity_Engine_System_Document.md §8, §12.
    Route::prefix('users')->group(function (): void {
        Route::middleware(['auth:sanctum'])->group(function (): void {
            Route::get('me', [MeController::class, 'show'])
                ->name('users.me.show');

            Route::middleware('idempotency')
                ->patch('me', [MeController::class, 'update'])
                ->name('users.me.update');

            Route::middleware(['throttle:identity-avatar-upload', 'idempotency'])
                ->post('me/avatar', [AvatarController::class, 'store'])
                ->name('users.me.avatar.store');

            // WP-20260530 — channel binding (add a second channel).
            Route::middleware(['throttle:channel-bind', 'idempotency'])
                ->post('me/channels/phone', [ChannelBindingController::class, 'startPhone'])
                ->name('users.me.channels.phone.start');

            Route::middleware(['throttle:channel-bind', 'idempotency'])
                ->post('me/channels/phone/confirm', [ChannelBindingController::class, 'confirmPhone'])
                ->name('users.me.channels.phone.confirm');

            Route::middleware(['throttle:channel-bind', 'idempotency'])
                ->post('me/channels/email', [ChannelBindingController::class, 'startEmail'])
                ->name('users.me.channels.email.start');
        });

        Route::middleware('throttle:identity-public-profile')
            ->get('{id}', [UserShowController::class, 'show'])
            ->whereUuid('id')
            ->name('users.show');
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
