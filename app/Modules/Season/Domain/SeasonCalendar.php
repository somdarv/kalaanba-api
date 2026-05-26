<?php

declare(strict_types=1);

namespace Kalaanba\Modules\Season\Domain;

use DateTimeImmutable;
use DateTimeZone;

/**
 * Pure date arithmetic for the Kalaanba season calendar. Framework-free
 * so Domain stays portable and easy to test. Every value-driven knob is
 * supplied via `SeasonConfig` (Constitution §1.2 — Configurability).
 *
 * Engine doc: docs/engines/season/Season_Engine_UPDATED.md §2 (calendar),
 * §9 (cutoffs), §12 (defaults). All times computed in UTC.
 */
final class SeasonCalendar
{
    public function __construct(private readonly SeasonConfig $config) {}

    /**
     * Build the canonical season window for the platform year that
     * contains the given instant. A season starts April 1 of year Y and
     * ends Feb 28/29 of year Y+1; March is the archive month.
     */
    public function windowFor(DateTimeImmutable $at): SeasonWindow
    {
        $tz = new DateTimeZone('UTC');
        $year = (int) $at->format('Y');
        $month = (int) $at->format('n');

        // If we're in Jan–Mar, the *active* season is the one that started
        // last April. From April onwards, the active season is this year's.
        $startYear = $month >= $this->config->startMonth ? $year : $year - 1;
        $endYear = $startYear + 1;

        $startsAt = $this->buildDate($startYear, $this->config->startMonth, $this->config->startDay, $tz);
        $endsAt = $this->buildEndDate($endYear, $tz);
        $participationCutoffAt = $this->buildDate(
            $startYear,
            $this->config->participationCutoffMonth,
            $this->config->participationCutoffDay,
            $tz,
        );

        $newRankedChallengeCutoffAt = $this->buildDate(
            $endYear,
            $this->config->newRankedChallengeCutoffMonth,
            $this->config->newRankedChallengeCutoffDay,
            $tz,
        );
        $rankedAcceptanceCutoffAt = $this->buildDate(
            $endYear,
            $this->config->rankedAcceptanceCutoffMonth,
            $this->config->rankedAcceptanceCutoffDay,
            $tz,
        );
        $closingWindowEndsAt = $this->buildDate($endYear, 3, $this->config->closingWindowEndDay, $tz)
            ->setTime(23, 59, 59);
        $archiveWindowEndsAt = $this->buildDate($endYear, 3, $this->config->archiveWindowEndDay, $tz)
            ->setTime(23, 59, 59);

        return new SeasonWindow(
            code: sprintf('%04d/%04d', $startYear, $endYear),
            startsAt: $startsAt,
            endsAt: $endsAt,
            participationCutoffAt: $participationCutoffAt,
            newRankedChallengeCutoffAt: $newRankedChallengeCutoffAt,
            rankedAcceptanceCutoffAt: $rankedAcceptanceCutoffAt,
            closingWindowEndsAt: $closingWindowEndsAt,
            archiveWindowEndsAt: $archiveWindowEndsAt,
        );
    }

    /**
     * Resolve which phase is in effect at `$at` for `$window`.
     * Pure function — same inputs always produce same output.
     *
     * Engine doc §2 + §9: peak (Dec–Jan inside active), run-in (Feb up to
     * end), closing (Mar 1 → closing_window_end_day), archived after that.
     * Preseason is the brief window between the previous season's archive
     * end and this season's start.
     */
    public function phaseAt(SeasonWindow $window, DateTimeImmutable $at): SeasonPhase
    {
        if ($at < $window->startsAt) {
            return SeasonPhase::Preseason;
        }

        if ($at > $window->archiveWindowEndsAt) {
            return SeasonPhase::Archived;
        }

        if ($at > $window->closingWindowEndsAt) {
            // Between closing end and archive end — still archived window.
            return SeasonPhase::Archived;
        }

        if ($at > $window->endsAt) {
            return SeasonPhase::Closing;
        }

        // Inside [startsAt, endsAt]. Decide active / peak / run-in.
        $month = (int) $at->format('n');

        if (in_array($month, $this->config->peakMonths, true)) {
            return SeasonPhase::Peak;
        }

        if ($month === $this->config->endMonth) {
            return SeasonPhase::RunIn;
        }

        return SeasonPhase::Active;
    }

    private function buildDate(int $year, int $month, int $day, DateTimeZone $tz): DateTimeImmutable
    {
        return new DateTimeImmutable(sprintf('%04d-%02d-%02d 00:00:00', $year, $month, $day), $tz);
    }

    /**
     * Season end is `end_month` `end_day` of `endYear`, leap-year aware:
     * if the configured day exceeds the month length, snap to last valid
     * day (Feb 29 in leap years, Feb 28 otherwise). Engine doc §2 calls
     * out "February 28/29" — leap year handling is computed (not config).
     */
    private function buildEndDate(int $endYear, DateTimeZone $tz): DateTimeImmutable
    {
        $month = $this->config->endMonth;
        $day = $this->config->endDay;
        $lastDayOfMonth = (int) (new DateTimeImmutable(sprintf('%04d-%02d-01', $endYear, $month), $tz))
            ->modify('last day of this month')
            ->format('j');
        $day = min($day, $lastDayOfMonth);

        return (new DateTimeImmutable(sprintf('%04d-%02d-%02d', $endYear, $month, $day), $tz))
            ->setTime(23, 59, 59);
    }
}
