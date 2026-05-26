<?php

declare(strict_types=1);

namespace Kalaanba\Modules\Season\Infrastructure\Config;

use Kalaanba\Modules\Season\Domain\SeasonConfig;
use Kalaanba\Modules\Season\Domain\SeasonConfigProvider;
use Kalaanba\Support\Config\Contracts\ConfigRepository;

/**
 * Read the `season.*` Admin Config keys and assemble a `SeasonConfig`.
 * Falls back to `SeasonConfig::defaults()` when a key is missing so
 * the platform stays bootable on a fresh database.
 *
 * Constitution §1.2 — every value-driven knob lives in Admin Config.
 */
final class AdminConfigSeasonConfigLoader implements SeasonConfigProvider
{
    public function __construct(private readonly ConfigRepository $config) {}

    public function load(): SeasonConfig
    {
        $defaults = SeasonConfig::defaults();

        return new SeasonConfig(
            startMonth: $this->int('season.start_month', $defaults->startMonth),
            startDay: $this->int('season.start_day', $defaults->startDay),
            endMonth: $this->int('season.end_month', $defaults->endMonth),
            endDay: $this->int('season.end_day', $defaults->endDay),
            participationCutoffMonth: $this->int('season.participation_cutoff_month', $defaults->participationCutoffMonth),
            participationCutoffDay: $this->int('season.participation_cutoff_day', $defaults->participationCutoffDay),
            closingWindowEndDay: $this->int('season.closing_window_end_day', $defaults->closingWindowEndDay),
            archiveWindowEndDay: $this->int('season.archive_window_end_day', $defaults->archiveWindowEndDay),
            newRankedChallengeCutoffMonth: $this->int('season.new_ranked_challenge_cutoff_month', $defaults->newRankedChallengeCutoffMonth),
            newRankedChallengeCutoffDay: $this->int('season.new_ranked_challenge_cutoff_day', $defaults->newRankedChallengeCutoffDay),
            rankedAcceptanceCutoffMonth: $this->int('season.ranked_acceptance_cutoff_month', $defaults->rankedAcceptanceCutoffMonth),
            rankedAcceptanceCutoffDay: $this->int('season.ranked_acceptance_cutoff_day', $defaults->rankedAcceptanceCutoffDay),
            peakMonths: $this->intList('season.peak_months', $defaults->peakMonths),
        );
    }

    private function int(string $key, int $fallback): int
    {
        $cv = $this->config->get($key, 'platform');
        if ($cv === null || $cv->value === '') {
            return $fallback;
        }

        return (int) $cv->value;
    }

    /**
     * @param  array<int>  $fallback
     * @return array<int>
     */
    private function intList(string $key, array $fallback): array
    {
        $cv = $this->config->get($key, 'platform');
        if ($cv === null || $cv->value === '') {
            return $fallback;
        }

        $decoded = json_decode($cv->value, true);
        if (! is_array($decoded)) {
            return $fallback;
        }

        $result = array_values(array_filter(array_map('intval', $decoded), fn (int $m) => $m >= 1 && $m <= 12));

        return $result === [] ? $fallback : $result;
    }
}
