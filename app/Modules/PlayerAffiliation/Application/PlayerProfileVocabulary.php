<?php

declare(strict_types=1);

namespace Kalaanba\Modules\PlayerAffiliation\Application;

use Kalaanba\Modules\PlayerAffiliation\Domain\PlayerAvailability;
use Kalaanba\Modules\PlayerAffiliation\Domain\PlayerMarketStatus;
use Kalaanba\Support\Config\Contracts\ConfigRepository;
use Throwable;

/**
 * Resolves the player-profile form vocabulary from Admin Configuration: which
 * options exist, what they are called, and what bounds a profile must respect
 * (engine doc §3, §6, §12).
 *
 * Single source for two callers — the meta endpoint that ships the vocabulary
 * to clients (ADR-0007) and the Form Request that validates against it. They
 * previously each read config themselves, which is how a set can be accepted
 * by the validator and never offered by the form.
 *
 * Every read is fallback-guarded: an unseeded or unreachable config store
 * degrades to the structural default rather than failing a form that is
 * otherwise perfectly valid. Labels are the exception — they fall back to the
 * internal key itself (ADR-0007 §2) rather than to an English string compiled
 * into the bundle, because a hardcoded label is the exact thing this class
 * exists to remove.
 */
final class PlayerProfileVocabulary
{
    /**
     * Must stay identical to the default in
     * `contracts/config/player/player.positions.yaml`. This is the value an
     * environment serves when the config row is missing, so if it drifts from
     * the contract the API quietly answers a different question than the docs
     * say it does. That is exactly what happened before 2026-08-19: config had
     * never been seeded locally, so every environment was serving these four
     * keys while the contract, the seeder and the client all expected more.
     */
    private const POSITION_FALLBACK = [
        'goalkeeper',
        'left_back',
        'centre_back',
        'right_back',
        'defensive_midfielder',
        'left_midfielder',
        'centre_midfielder',
        'right_midfielder',
        'attacking_midfielder',
        'left_winger',
        'right_winger',
        'second_striker',
        'striker',
    ];

    private const NAME_MAX_FALLBACK = 80;

    private const STAGE_NAME_MAX_FALLBACK = 40;

    private const NUMBER_MIN_FALLBACK = 1;

    private const NUMBER_MAX_FALLBACK = 99;

    public function __construct(
        private readonly ConfigRepository $config,
    ) {}

    /**
     * The full vocabulary as the contract shapes it.
     *
     * @param  string|null  $locale  Primary language tag from Accept-Language.
     *                               Selects a locale-suffixed label map when one
     *                               is configured; labels only, never keys.
     * @return array<string, mixed>
     */
    public function toMetaView(?string $locale = null): array
    {
        $positionLabels = $this->labelMap('player.positions.labels', $locale);
        $positionAbbreviations = $this->labelMap('player.positions.abbreviations', $locale);
        $positionNotes = $this->labelMap('player.positions.descriptions', $locale);
        $availabilityLabels = $this->labelMap('player.availability.labels', $locale);
        $availabilityNotes = $this->labelMap('player.availability.descriptions', $locale);
        $marketLabels = $this->labelMap('player.market_status.labels', $locale);
        $confidenceLabels = $this->labelMap('player.card_confidence.labels', $locale);

        $bounds = $this->bounds();

        return [
            // `abbreviation` is the short form drawn on the pitch picker. It is
            // a display string like `label`, so it is configurable and
            // translatable rather than derived in the client (Law 4). It falls
            // back to the label so a new key added in Admin Config is never
            // undrawable. Additive to the v1 contract, so non-breaking (§7).
            'positions' => array_map(
                fn (string $key): array => [
                    'key' => $key,
                    'label' => $positionLabels[$key] ?? $key,
                    'abbreviation' => $positionAbbreviations[$key] ?? ($positionLabels[$key] ?? $key),
                    'description' => $positionNotes[$key] ?? null,
                ],
                $this->positionKeys(),
            ),
            'availability' => array_map(
                fn (string $key): array => [
                    'key' => $key,
                    'label' => $availabilityLabels[$key] ?? $key,
                    'description' => $availabilityNotes[$key] ?? null,
                ],
                $this->availabilityKeys(),
            ),
            'availability_default' => $this->availabilityDefault()->value,
            'market_statuses' => array_map(
                fn (PlayerMarketStatus $status): array => [
                    'key' => $status->value,
                    'label' => $marketLabels[$status->value] ?? $status->value,
                ],
                PlayerMarketStatus::cases(),
            ),
            'preferred_number' => [
                'min' => $bounds['preferred_number_min'],
                'max' => $bounds['preferred_number_max'],
                'quick_picks' => $this->quickPicks(
                    $bounds['preferred_number_min'],
                    $bounds['preferred_number_max'],
                ),
            ],
            'name' => [
                'max_length' => $bounds['name_max_length'],
                'stage_name_max_length' => $bounds['stage_name_max_length'],
            ],
            // Card-confidence labels (§14). Keys come from the LADDER
            // (`player.card_confidence.tiers`), not from the label map, so a
            // half-written map degrades to raw keys instead of dropping a tier
            // a player can actually reach. Additive to the v1 contract and
            // therefore non-breaking (§7).
            'card_confidence' => array_map(
                fn (string $key): array => [
                    'key' => $key,
                    'label' => $confidenceLabels[$key] ?? $key,
                ],
                $this->confidenceTierKeys(),
            ),
        ];
    }

    /**
     * Allowed primary-position keys, in configured order (order is
     * presentation and belongs to config — see `player.positions`).
     *
     * @return list<string>
     */
    public function positionKeys(): array
    {
        $decoded = $this->jsonConfig('player.positions');
        if ($decoded === null) {
            return self::POSITION_FALLBACK;
        }

        $keys = array_values(array_filter($decoded, 'is_string'));

        return $keys === [] ? self::POSITION_FALLBACK : $keys;
    }

    /**
     * Tier keys from the confidence ladder, ascending.
     *
     * Reads the ladder rather than the label map because the ladder is what
     * decides which tiers EXIST. Falls back to the same structural default
     * `CardConfidenceLadder` uses, so the vocabulary and the resolver can never
     * disagree about the set.
     *
     * @return list<string>
     */
    public function confidenceTierKeys(): array
    {
        $decoded = $this->jsonConfig('player.card_confidence.tiers');
        if ($decoded === null) {
            return ['provisional', 'growing', 'verified'];
        }

        $tiers = [];
        foreach ($decoded as $entry) {
            if (! is_array($entry)) {
                continue;
            }
            $key = $entry['key'] ?? null;
            $min = $entry['min_confirmed_matches'] ?? null;
            if (! is_string($key) || $key === '' || ! is_numeric($min)) {
                continue;
            }
            $tiers[$key] = (int) $min;
        }

        if ($tiers === []) {
            return ['provisional', 'growing', 'verified'];
        }

        asort($tiers);

        return array_keys($tiers);
    }

    /**
     * Availability keys come from the enum, not from config: §12 fixes the
     * state set and only the labels are configurable.
     *
     * @return list<string>
     */
    public function availabilityKeys(): array
    {
        return array_map(
            static fn (PlayerAvailability $case): string => $case->value,
            PlayerAvailability::cases(),
        );
    }

    /**
     * Length and range limits a profile must respect.
     *
     * @return array{name_max_length:int, stage_name_max_length:int, preferred_number_min:int, preferred_number_max:int}
     */
    public function bounds(): array
    {
        $min = $this->intConfig('player.profile.preferred_number_min', self::NUMBER_MIN_FALLBACK);
        $max = $this->intConfig('player.profile.preferred_number_max', self::NUMBER_MAX_FALLBACK);

        // A misconfigured range would otherwise reject every number.
        if ($max < $min) {
            [$min, $max] = [self::NUMBER_MIN_FALLBACK, self::NUMBER_MAX_FALLBACK];
        }

        return [
            'name_max_length' => $this->intConfig(
                'player.profile.name_max_length',
                self::NAME_MAX_FALLBACK,
            ),
            'stage_name_max_length' => $this->intConfig(
                'player.profile.stage_name_max_length',
                self::STAGE_NAME_MAX_FALLBACK,
            ),
            'preferred_number_min' => $min,
            'preferred_number_max' => $max,
        ];
    }

    /** Availability preselected when the caller omits one (§12). */
    public function availabilityDefault(): PlayerAvailability
    {
        $raw = $this->stringConfig('player.availability.default');

        return $raw === null
            ? PlayerAvailability::Unknown
            : (PlayerAvailability::tryFrom($raw) ?? PlayerAvailability::Unknown);
    }

    /**
     * One-tap shirt numbers. Anything outside the authoritative range is
     * dropped here rather than served and then rejected on submit.
     *
     * @return list<int>
     */
    private function quickPicks(int $min, int $max): array
    {
        $decoded = $this->jsonConfig('player.profile.preferred_number_quick_picks');
        if ($decoded === null) {
            return [];
        }

        $picks = [];
        foreach ($decoded as $candidate) {
            if (! is_int($candidate) && ! (is_string($candidate) && ctype_digit($candidate))) {
                continue;
            }
            $value = (int) $candidate;
            if ($value >= $min && $value <= $max && ! in_array($value, $picks, true)) {
                $picks[] = $value;
            }
        }

        return $picks;
    }

    /**
     * Key → label map, preferring the most specific locale variant configured.
     * For `fr-FR` that is `<key>.fr-fr`, then `<key>.fr`, then the unsuffixed
     * `<key>` — the usual narrowing chain, so a region only needs its own map
     * when it actually differs from the language.
     *
     * @return array<string, string>
     */
    private function labelMap(string $key, ?string $locale): array
    {
        $decoded = null;

        foreach ($this->localeKeys($key, $locale) as $candidate) {
            $decoded = $this->jsonConfig($candidate);
            if ($decoded !== null) {
                break;
            }
        }

        if ($decoded === null) {
            return [];
        }

        $labels = [];
        foreach ($decoded as $optionKey => $label) {
            if (is_string($optionKey) && is_string($label) && $label !== '') {
                $labels[$optionKey] = $label;
            }
        }

        return $labels;
    }

    /**
     * Config keys to try for a label map, most specific first.
     *
     * @return list<string>
     */
    private function localeKeys(string $key, ?string $locale): array
    {
        if ($locale === null || $locale === '') {
            return [$key];
        }

        $keys = [$key.'.'.$locale];

        $language = strstr($locale, '-', true);
        if (is_string($language) && $language !== '') {
            $keys[] = $key.'.'.$language;
        }

        $keys[] = $key;

        return $keys;
    }

    /**
     * @return array<array-key, mixed>|null
     */
    private function jsonConfig(string $key): ?array
    {
        $raw = $this->stringConfig($key);
        if ($raw === null) {
            return null;
        }

        $decoded = json_decode($raw, true);

        return is_array($decoded) ? $decoded : null;
    }

    private function intConfig(string $key, int $fallback): int
    {
        $raw = $this->stringConfig($key);

        return $raw === null || ! is_numeric($raw) ? $fallback : (int) $raw;
    }

    private function stringConfig(string $key): ?string
    {
        try {
            $value = $this->config->get($key);
        } catch (Throwable) {
            // Config store unreachable — callers fall back to structural
            // defaults rather than failing the request.
            return null;
        }

        return $value === null ? null : $value->value;
    }
}
