<?php

declare(strict_types=1);
use Kalaanba\Modules\Analytics\Domain\EventSchema;
use Kalaanba\Modules\Analytics\Schemas\SchemaCatalogue;

/*
|--------------------------------------------------------------------------
| Architecture Tests
|--------------------------------------------------------------------------
|
| Enforces engineering standards from
| .github/instructions/engineering-standards.instructions.md and the
| layering rules in app/Modules/README.md.
|
| As modules are added under app/Modules/<EngineName>/, append additional
| module-scoped arch tests at the marker below.
|
*/

arch('No debug statements anywhere')
    ->expect(['dd', 'dump', 'var_dump', 'ray', 'print_r'])
    ->not->toBeUsed();

arch('App namespace uses strict types')
    ->expect('App')
    ->toUseStrictTypes();

arch('Modules namespace uses strict types')
    ->expect('Kalaanba\Modules')
    ->toUseStrictTypes();

arch('Support namespace uses strict types')
    ->expect('Kalaanba\Support')
    ->toUseStrictTypes();

// ============================================================
// EVENT BUS ARCHITECTURE TESTS
// ============================================================

arch('EventBus support classes use strict types')
    ->expect('Kalaanba\Support\EventBus')
    ->toUseStrictTypes();

arch('OutboxEnvelope is a readonly class')
    ->expect('Kalaanba\Support\EventBus\OutboxEnvelope')
    ->toBeReadonly();

arch('OutboxWriter does not open its own DB transaction')
    ->expect('Kalaanba\Support\EventBus\OutboxWriter')
    ->not->toUse('Illuminate\Support\Facades\DB::transaction');

// ============================================================
// ANALYTICS SCHEMA REGISTRY DRIFT GUARD
// ============================================================
//
// Build_Plan.md §0.4 — "Schema-validation test that fails CI if event
// shape drifts." Every analytics event schema MUST be surfaced through
// SchemaCatalogue::all() AND remain self-validating (no overlap between
// required/optional, valid event name, etc.).

test('every registered analytics schema validates its own definition', function (): void {
    $catalogue = SchemaCatalogue::all();

    expect($catalogue)->not->toBeEmpty();

    foreach ($catalogue as $schema) {
        expect($schema)->toBeInstanceOf(EventSchema::class);
        expect($schema->schemaVersion)->toBeGreaterThanOrEqual(1);
        expect(preg_match('/^[a-z][a-z0-9_]*\.[a-z][a-z0-9_]*$/', $schema->eventName))->toBe(1);
    }
});

test('analytics schema keys are unique across the catalogue', function (): void {
    $keys = array_map(
        static fn (EventSchema $s): string => $s->key(),
        SchemaCatalogue::all(),
    );

    expect($keys)->toEqual(array_values(array_unique($keys)));
});

arch('Analytics Domain has no framework dependencies')
    ->expect('Kalaanba\Modules\Analytics\Domain')
    ->not->toUse(['Illuminate', 'Symfony', 'Laravel']);

// ============================================================
// PLATFORM AUTH (cross-cutting Support)
// ============================================================

arch('Role enum lives in Support and has no framework deps')
    ->expect('Kalaanba\Support\Auth\Role')
    ->not->toUse(['Illuminate', 'Symfony', 'Laravel']);

arch('Idempotency middleware lives in Support')
    ->expect('Kalaanba\Support\Http\Middleware')
    ->toOnlyBeUsedIn(['App\\', 'Kalaanba\\Support']);

arch('Engine modules do not depend on App\\Models\\User directly')
    ->expect('Kalaanba\Modules')
    ->not->toUse('App\Models\User');

// OTP support lives only in the Support layer + is consumed only by the App
// HTTP layer (no engine module should mint, store, or verify OTPs).
arch('OTP machinery is confined to Support and App')
    ->expect('Kalaanba\Support\Auth\Otp')
    ->toOnlyBeUsedIn(['App\\', 'Kalaanba\\Support']);

arch('Scope resolver is consumed only by Support and App')
    ->expect('Kalaanba\Support\Auth\Scope')
    ->toOnlyBeUsedIn(['App\\', 'Kalaanba\\Support']);

arch('PhoneHash is consumed only by Support, App, and Database factories')
    ->expect('Kalaanba\Support\Auth\PhoneHash')
    ->toOnlyBeUsedIn(['App\\', 'Kalaanba\\Support', 'Database\\Factories']);

// Admin audit machinery — write side is Support; read side is App. Engine
// modules MUST NOT touch the audit writer directly (Constitution Law 5).
arch('Admin audit machinery is confined to Support and App')
    ->expect('Kalaanba\Support\Audit')
    ->toOnlyBeUsedIn(['App\\', 'Kalaanba\\Support']);

arch('AdminAuditEntry is a readonly value object')
    ->expect('Kalaanba\Support\Audit\AdminAuditEntry')
    ->toBeReadonly();

// ============================================================
// MODULE-SCOPED ARCHITECTURE TESTS — add below as modules land
// ============================================================
//
// Template for each new module (replace <Engine>):
//
// arch('<Engine> Domain has no framework dependencies')
//     ->expect('Kalaanba\Modules\<Engine>\Domain')
//     ->not->toUse(['Illuminate', 'Symfony', 'Laravel']);
//
// arch('<Engine> Domain has no facades')
//     ->expect('Kalaanba\Modules\<Engine>\Domain')
//     ->not->toUse('Illuminate\Support\Facades');
//
// arch('<Engine> Application has no facades')
//     ->expect('Kalaanba\Modules\<Engine>\Application')
//     ->not->toUse('Illuminate\Support\Facades');

// ============================================================
// SEASON ENGINE
// ============================================================

arch('Season Domain has no framework dependencies')
    ->expect('Kalaanba\Modules\Season\Domain')
    ->not->toUse(['Illuminate', 'Symfony', 'Laravel']);

arch('Season Application does not reach into Infrastructure')
    ->expect('Kalaanba\Modules\Season\Application')
    ->not->toUse('Kalaanba\Modules\Season\Infrastructure');

arch('Season DTOs are readonly')
    ->expect([
        'Kalaanba\Modules\Season\Domain\SeasonView',
        'Kalaanba\Modules\Season\Domain\SeasonWindow',
        'Kalaanba\Modules\Season\Domain\SeasonConfig',
    ])
    ->toBeReadonly();

// ============================================================
// ZONE ENGINE
// ============================================================

arch('Zone Domain has no framework dependencies')
    ->expect('Kalaanba\Modules\Zone\Domain')
    ->not->toUse(['Illuminate', 'Symfony', 'Laravel']);

arch('Zone Application does not reach into Infrastructure')
    ->expect('Kalaanba\Modules\Zone\Application')
    ->not->toUse('Kalaanba\Modules\Zone\Infrastructure');

arch('Zone DTOs are readonly')
    ->expect([
        'Kalaanba\Modules\Zone\Domain\Country',
        'Kalaanba\Modules\Zone\Domain\Region',
        'Kalaanba\Modules\Zone\Domain\CityHub',
        'Kalaanba\Modules\Zone\Domain\Zone',
        'Kalaanba\Modules\Zone\Domain\Area',
        'Kalaanba\Modules\Zone\Domain\AreaSuggestion',
    ])
    ->toBeReadonly();
