<?php

declare(strict_types=1);

use Kalaanba\Modules\Season\Domain\SeasonCalendar;
use Kalaanba\Modules\Season\Domain\SeasonConfig;
use Kalaanba\Modules\Season\Domain\SeasonPhase;

/**
 * Pure unit tests for SeasonCalendar. No framework boot — Domain is
 * framework-free per deptrac rules.
 *
 * Engine doc: docs/engines/season/Season_Engine_UPDATED.md §2 + §9 + §12.
 */
beforeEach(function (): void {
    $this->calendar = new SeasonCalendar(SeasonConfig::defaults());
});

it('builds a season window keyed by the April-1 to April-1 cut', function (): void {
    // June 15, 2026 sits inside the 2026/2027 season (started Apr 1 2026).
    $at = new DateTimeImmutable('2026-06-15T10:00:00+00:00');

    $window = $this->calendar->windowFor($at);

    expect($window->code)->toBe('2026/2027')
        ->and($window->startsAt->format('Y-m-d'))->toBe('2026-04-01')
        ->and($window->endsAt->format('Y-m-d'))->toBe('2027-02-28');
});

it('keeps the season anchored to the previous April when called in January', function (): void {
    // Jan 10 2027 is still inside the 2026/2027 season (Jan = peak).
    $at = new DateTimeImmutable('2027-01-10T10:00:00+00:00');

    $window = $this->calendar->windowFor($at);

    expect($window->code)->toBe('2026/2027');
});

it('honours leap years by snapping end_day to Feb 29 when applicable', function (): void {
    // 2027/2028 season ends in Feb 2028 — 2028 IS a leap year, so Feb 29.
    $at = new DateTimeImmutable('2027-06-15T10:00:00+00:00');

    $window = $this->calendar->windowFor($at);

    expect($window->endsAt->format('Y-m-d'))->toBe('2028-02-29');
});

it('detects peak phase during December and January', function (): void {
    $window = $this->calendar->windowFor(new DateTimeImmutable('2026-12-15T00:00:00+00:00'));

    expect($this->calendar->phaseAt($window, new DateTimeImmutable('2026-12-15T00:00:00+00:00')))
        ->toBe(SeasonPhase::Peak)
        ->and($this->calendar->phaseAt($window, new DateTimeImmutable('2027-01-20T00:00:00+00:00')))
        ->toBe(SeasonPhase::Peak);
});

it('detects run-in phase during the configured end month', function (): void {
    $window = $this->calendar->windowFor(new DateTimeImmutable('2026-06-01T00:00:00+00:00'));

    $phase = $this->calendar->phaseAt($window, new DateTimeImmutable('2027-02-10T00:00:00+00:00'));

    expect($phase)->toBe(SeasonPhase::RunIn);
});

it('flips to closing after end_at and to archived after archive_window_end', function (): void {
    $window = $this->calendar->windowFor(new DateTimeImmutable('2026-06-01T00:00:00+00:00'));

    expect($this->calendar->phaseAt($window, new DateTimeImmutable('2027-03-05T00:00:00+00:00')))
        ->toBe(SeasonPhase::Closing)
        ->and($this->calendar->phaseAt($window, new DateTimeImmutable('2027-03-20T00:00:00+00:00')))
        ->toBe(SeasonPhase::Archived)
        ->and($this->calendar->phaseAt($window, new DateTimeImmutable('2027-04-01T00:00:00+00:00')))
        ->toBe(SeasonPhase::Archived);
});

it('treats pre-start instants as preseason', function (): void {
    $window = $this->calendar->windowFor(new DateTimeImmutable('2026-06-01T00:00:00+00:00'));

    $phase = $this->calendar->phaseAt($window, new DateTimeImmutable('2026-03-25T00:00:00+00:00'));

    expect($phase)->toBe(SeasonPhase::Preseason);
});

it('exposes only the three RP-awarding phases', function (): void {
    expect(SeasonPhase::Active->awardsSeasonRp())->toBeTrue()
        ->and(SeasonPhase::Peak->awardsSeasonRp())->toBeTrue()
        ->and(SeasonPhase::RunIn->awardsSeasonRp())->toBeTrue()
        ->and(SeasonPhase::Preseason->awardsSeasonRp())->toBeFalse()
        ->and(SeasonPhase::Closing->awardsSeasonRp())->toBeFalse()
        ->and(SeasonPhase::Archived->awardsSeasonRp())->toBeFalse();
});
