<?php

declare(strict_types=1);

namespace Kalaanba\Modules\Club\Application;

use Kalaanba\Modules\Club\Domain\ClubNamePolicy;
use Kalaanba\Modules\Club\Domain\ClubTier;
use Kalaanba\Modules\Club\Domain\ReservedClubName;
use Kalaanba\Support\Config\Contracts\ConfigRepository;
use Throwable;

/**
 * Resolves the club-creation vocabulary from Admin Configuration: which tiers
 * and club types exist, which tier each type belongs to, what they are called,
 * the name bounds a club must respect, and the reserved-name policy
 * (engine doc §3, §5; ADR-0007 for the pattern, ADR-0017 for the name rule).
 *
 * Single source for three callers — the meta endpoint that ships the
 * vocabulary to clients, the Form Request that validates a submission against
 * it, and {@see CreateClub} which enforces the name policy. They must not each
 * read config themselves: that is how a type gets accepted by the validator and
 * never offered by the form.
 *
 * Every read is fallback-guarded. An unseeded or unreachable config store
 * degrades to the structural default rather than failing a creation that is
 * otherwise perfectly valid. Labels are the exception and fall back to the
 * internal key (ADR-0007 §2), because a hardcoded English label is the exact
 * thing this class exists to remove.
 *
 * **The reserved-name list never reaches {@see self::toMetaView()}.** Whether a
 * name may be used is a verdict, verdicts are backend truth (Law 3), and
 * publishing the list publishes the map for routing around it.
 */
final class ClubVocabulary
{
    /**
     * Must stay identical to the default in
     * `contracts/config/club/club.types.yaml`. This is what an environment
     * serves when the config row is missing, so drift from the contract means
     * the API quietly answers a different question than the docs say.
     */
    private const TYPE_FALLBACK = [
        'community', 'informal', 'school', 'academy', 'corporate',
        'religious', 'institution', 'facility', 'registered',
    ];

    private const TYPE_TIER_FALLBACK = [
        'community' => 'amateur',
        'informal' => 'amateur',
        'school' => 'amateur',
        'corporate' => 'amateur',
        'religious' => 'amateur',
        'facility' => 'amateur',
        'academy' => 'professional',
        'institution' => 'professional',
        'registered' => 'professional',
    ];

    private const IGNORED_TOKEN_FALLBACK = [
        'fc', 'f.c.', 'sc', 'afc', 'football', 'club', 'team',
    ];

    private const NAME_MIN_FALLBACK = 2;

    private const NAME_MAX_FALLBACK = 120;

    public function __construct(
        private readonly ConfigRepository $config,
    ) {}

    /**
     * The full vocabulary as `contracts/api/club/get-clubs-meta.v1.yaml` shapes it.
     *
     * @param  string|null  $locale  Primary language tag from Accept-Language.
     *                               Selects a locale-suffixed label map when one
     *                               is configured; labels only, never keys.
     * @return array<string, mixed>
     */
    public function toMetaView(?string $locale = null): array
    {
        $tierLabels = $this->labelMap('club.tiers.labels', $locale);
        $tierNotes = $this->labelMap('club.tiers.descriptions', $locale);
        $typeLabels = $this->labelMap('club.types.labels', $locale);
        $typeNotes = $this->labelMap('club.types.descriptions', $locale);

        $typeTiers = $this->typeTierMap();
        $bounds = $this->nameBounds();
        $defaultTier = $this->tierKeys()[0];

        return [
            'tiers' => array_map(
                fn (string $key): array => [
                    'key' => $key,
                    'label' => $tierLabels[$key] ?? $key,
                    'description' => $tierNotes[$key] ?? null,
                ],
                $this->tierKeys(),
            ),
            'types' => array_map(
                fn (string $key): array => [
                    'key' => $key,
                    'label' => $typeLabels[$key] ?? $key,
                    'description' => $typeNotes[$key] ?? null,
                    // A type missing from the tier map reports the FIRST tier,
                    // which is the safer default: that tier may not claim a
                    // reserved name, so a half-written map cannot accidentally
                    // open the professional door to a type nobody placed.
                    'tier' => $typeTiers[$key] ?? $defaultTier,
                ],
                $this->typeKeys(),
            ),
            'name' => [
                'min_length' => $bounds['min'],
                'max_length' => $bounds['max'],
            ],
        ];
    }

    /**
     * Tier keys in configured order. Order is presentation and belongs to
     * config, not to the client's sort.
     *
     * Filtered against {@see ClubTier}: config owns the order and the labels,
     * but each tier carries distinct behaviour in this engine, so a key with no
     * code path behind it is dropped rather than offered as a door that leads
     * nowhere.
     *
     * @return non-empty-list<string>
     */
    public function tierKeys(): array
    {
        $decoded = $this->jsonConfig('club.tiers');
        $keys = [];

        foreach (is_array($decoded) ? $decoded : [] as $candidate) {
            if (is_string($candidate) && ClubTier::tryFrom($candidate) !== null) {
                $keys[] = $candidate;
            }
        }

        return $keys === []
            ? array_map(static fn (ClubTier $tier): string => $tier->value, ClubTier::cases())
            : $keys;
    }

    /**
     * Allowed club-type keys, in configured order.
     *
     * @return list<string>
     */
    public function typeKeys(): array
    {
        $decoded = $this->jsonConfig('club.types');
        if ($decoded === null) {
            return self::TYPE_FALLBACK;
        }

        $keys = array_values(array_filter($decoded, 'is_string'));

        return $keys === [] ? self::TYPE_FALLBACK : $keys;
    }

    /**
     * Club-type key → tier key.
     *
     * @return array<string, string>
     */
    public function typeTierMap(): array
    {
        $decoded = $this->jsonConfig('club.types.tier');
        if ($decoded === null) {
            return self::TYPE_TIER_FALLBACK;
        }

        $map = [];
        foreach ($decoded as $type => $tier) {
            if (is_string($type) && is_string($tier) && ClubTier::tryFrom($tier) !== null) {
                $map[$type] = $tier;
            }
        }

        return $map === [] ? self::TYPE_TIER_FALLBACK : $map;
    }

    /**
     * The club types belonging to one tier, in configured order. Used by the
     * Form Request to refuse a type that does not match the submitted door.
     *
     * @return list<string>
     */
    public function typeKeysForTier(ClubTier $tier): array
    {
        $map = $this->typeTierMap();
        $defaultTier = $this->tierKeys()[0];

        return array_values(array_filter(
            $this->typeKeys(),
            static fn (string $type): bool => ($map[$type] ?? $defaultTier) === $tier->value,
        ));
    }

    /**
     * @return array{min:int, max:int}
     */
    public function nameBounds(): array
    {
        $min = $this->intConfig('club.profile.name_min_length', self::NAME_MIN_FALLBACK);
        $max = $this->intConfig('club.profile.name_max_length', self::NAME_MAX_FALLBACK);

        // A misconfigured range would otherwise reject every name.
        if ($max < $min) {
            return ['min' => self::NAME_MIN_FALLBACK, 'max' => self::NAME_MAX_FALLBACK];
        }

        return ['min' => $min, 'max' => $max];
    }

    /**
     * The name policy, built from config (ADR-0017).
     *
     * An unreadable or unseeded `club.name.reserved_terms` yields a policy with
     * no terms, which refuses nothing. That is the deliberate choice: failing
     * open lets a club be created under a famous name until config is fixed,
     * while failing closed would refuse every name on the platform. The first
     * is recoverable by an admin archiving one club; the second takes creation
     * down entirely.
     */
    public function namePolicy(): ClubNamePolicy
    {
        return new ClubNamePolicy($this->reservedNames(), $this->ignoredTokens());
    }

    /**
     * @return list<ReservedClubName>
     */
    private function reservedNames(): array
    {
        $decoded = $this->jsonConfig('club.name.reserved_terms');
        if ($decoded === null) {
            return [];
        }

        $names = [];
        foreach ($decoded as $entry) {
            if (! is_array($entry)) {
                continue;
            }

            $canonical = $entry['canonical'] ?? null;
            if (! is_string($canonical) || trim($canonical) === '') {
                continue;
            }

            $aliases = [];
            foreach (is_array($entry['aliases'] ?? null) ? $entry['aliases'] : [] as $alias) {
                if (is_string($alias) && trim($alias) !== '') {
                    $aliases[] = $alias;
                }
            }

            $names[] = new ReservedClubName($canonical, $aliases);
        }

        return $names;
    }

    /**
     * @return list<string>
     */
    private function ignoredTokens(): array
    {
        $decoded = $this->jsonConfig('club.name.ignored_tokens');
        if ($decoded === null) {
            return self::IGNORED_TOKEN_FALLBACK;
        }

        $tokens = array_values(array_filter($decoded, 'is_string'));

        return $tokens === [] ? self::IGNORED_TOKEN_FALLBACK : $tokens;
    }

    /**
     * Key → label map, preferring the most specific locale variant configured.
     * For `fr-FR` that is `<key>.fr-fr`, then `<key>.fr`, then the unsuffixed
     * `<key>` — the usual narrowing chain.
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
        try {
            $value = $this->config->get($key);
        } catch (Throwable) {
            return null;
        }

        if ($value === null) {
            return null;
        }

        $decoded = json_decode((string) $value->value, true);

        return is_array($decoded) ? $decoded : null;
    }

    private function intConfig(string $key, int $fallback): int
    {
        try {
            $value = $this->config->get($key);
        } catch (Throwable) {
            return $fallback;
        }

        if ($value === null || ! is_numeric($value->value)) {
            return $fallback;
        }

        return (int) $value->value;
    }
}
