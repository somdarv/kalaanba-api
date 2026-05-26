<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Kalaanba\Modules\Season\Application\SeasonTicker;
use Kalaanba\Modules\Season\Domain\SeasonPhase;
use Kalaanba\Modules\Season\Infrastructure\Eloquent\SeasonRecord;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->ticker = $this->app->make(SeasonTicker::class);
});

it('upserts the current season and emits exactly one phase change per boundary', function (): void {
    // Mid-active phase — should bootstrap the season at Active with no transition.
    $emitted = $this->ticker->tick(new DateTimeImmutable('2026-06-15T12:00:00+00:00'));

    expect($emitted['season.phase_changed'])->toBe(0);

    // First tick still creates the row at the desired phase (Active in June).
    $season = SeasonRecord::query()->where('code', '2026/2027')->first();
    expect($season)->not->toBeNull()
        ->and($season->phase)->toBe(SeasonPhase::Active->value);
});

it('emits season.phase_changed when crossing into peak', function (): void {
    // Bootstrap in active.
    $this->ticker->tick(new DateTimeImmutable('2026-06-15T12:00:00+00:00'));

    // Re-tick in peak.
    $emitted = $this->ticker->tick(new DateTimeImmutable('2026-12-15T12:00:00+00:00'));

    expect($emitted['season.phase_changed'])->toBe(1);

    $rows = DB::table('outbox_events')
        ->where('event_name', 'season.phase_changed')
        ->get();

    expect($rows)->toHaveCount(1);
});

it('is idempotent — running tick twice does not double-emit the same boundary', function (): void {
    $this->ticker->tick(new DateTimeImmutable('2026-06-15T12:00:00+00:00'));
    $this->ticker->tick(new DateTimeImmutable('2026-12-15T12:00:00+00:00'));
    $this->ticker->tick(new DateTimeImmutable('2026-12-15T12:30:00+00:00'));

    $count = DB::table('outbox_events')
        ->where('event_name', 'season.phase_changed')
        ->count();

    expect($count)->toBe(1);
});

it('emits season.rp_reset_due exactly once when the season is archived', function (): void {
    $this->ticker->tick(new DateTimeImmutable('2026-06-15T12:00:00+00:00'));
    $this->ticker->tick(new DateTimeImmutable('2027-03-25T12:00:00+00:00'));
    // Second tick at archived must not duplicate.
    $this->ticker->tick(new DateTimeImmutable('2027-03-26T12:00:00+00:00'));

    $count = DB::table('outbox_events')
        ->where('event_name', 'season.rp_reset_due')
        ->count();

    expect($count)->toBe(1);
});

it('emits season.cutoff_passed once per cutoff across multiple ticks', function (): void {
    // First tick after participation cutoff (Jul 31).
    $this->ticker->tick(new DateTimeImmutable('2026-08-05T12:00:00+00:00'));
    $this->ticker->tick(new DateTimeImmutable('2026-08-06T12:00:00+00:00'));

    $count = DB::table('outbox_events')
        ->where('event_name', 'season.cutoff_passed')
        ->where('payload->>cutoff_key', 'participation_cutoff_at')
        ->count();

    // Postgres-specific json path operator above won't run on SQLite — fall back.
    if ($count === 0) {
        $count = DB::table('outbox_events')
            ->where('event_name', 'season.cutoff_passed')
            ->get()
            ->filter(function ($row): bool {
                $payload = json_decode($row->payload, true);

                return ($payload['payload']['cutoff_key'] ?? null) === 'participation_cutoff_at';
            })
            ->count();
    }

    expect($count)->toBe(1);
});
