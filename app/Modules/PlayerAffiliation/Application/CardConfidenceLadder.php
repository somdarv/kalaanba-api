<?php

declare(strict_types=1);

namespace Kalaanba\Modules\PlayerAffiliation\Application;

use Kalaanba\Modules\PlayerAffiliation\Domain\CardConfidence;
use Kalaanba\Support\Config\Contracts\ConfigRepository;
use Throwable;

/**
 * Resolves a confirmed-match count into a card-confidence tier (engine doc §14).
 *
 * The ladder is Admin Configuration (`player.card_confidence.tiers`), not a
 * constant, because §14 leaves the thresholds to the operator and Constitution
 * Law 2 forbids compiling one in. Moving "verified" from 10 matches to 15 is a
 * config edit that re-tiers every card at once, with no deploy.
 *
 * Resolution happens HERE and never in a client. The config store is
 * effective-dated, so reconstructing what a card looked like last season means
 * reading the thresholds in force then — a client cannot do that, and one that
 * compares a count to a number it was handed has quietly forked the rule.
 *
 * Fallback-guarded like the rest of the vocabulary: an unseeded or unreachable
 * config store degrades to the structural default rather than failing a profile
 * read over a display label.
 */
final class CardConfidenceLadder
{
    /**
     * Must stay identical to the default in
     * `contracts/config/player/player.card_confidence.tiers.yaml`. This is what
     * an environment serves when the row is missing, so drift here means the
     * API answers a different question than the contract documents.
     *
     * @var list<array{key: string, min_confirmed_matches: int}>
     */
    private const TIER_FALLBACK = [
        ['key' => 'provisional', 'min_confirmed_matches' => 0],
        ['key' => 'growing', 'min_confirmed_matches' => 3],
        ['key' => 'verified', 'min_confirmed_matches' => 10],
    ];

    public function __construct(
        private readonly ConfigRepository $config,
    ) {}

    public function resolve(int $confirmedMatches): CardConfidence
    {
        $tiers = $this->tiers();

        $current = $tiers[0];
        $next = null;

        foreach ($tiers as $tier) {
            if ($confirmedMatches >= $tier['min_confirmed_matches']) {
                $current = $tier;

                continue;
            }

            // Ascending order, so the first threshold above the count is the
            // next rung. Everything after it is further away.
            $next = $tier;
            break;
        }

        return new CardConfidence(
            tier: $current['key'],
            confirmedMatches: $confirmedMatches,
            nextTier: $next['key'] ?? null,
            matchesToNextTier: $next === null
                ? null
                : max(0, $next['min_confirmed_matches'] - $confirmedMatches),
        );
    }

    /**
     * The ladder, ascending, guaranteed non-empty and guaranteed to start at 0.
     *
     * A ladder whose lowest rung sits above 0 would leave a brand-new player
     * with no tier at all, so a malformed value is rejected wholesale rather
     * than patched: half-honouring a broken config is harder to debug than
     * ignoring it.
     *
     * @return non-empty-list<array{key: string, min_confirmed_matches: int}>
     */
    private function tiers(): array
    {
        $decoded = $this->jsonConfig('player.card_confidence.tiers');
        if ($decoded === null) {
            return self::TIER_FALLBACK;
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
            $tiers[] = ['key' => $key, 'min_confirmed_matches' => (int) $min];
        }

        if ($tiers === []) {
            return self::TIER_FALLBACK;
        }

        usort($tiers, static fn (array $a, array $b): int => $a['min_confirmed_matches'] <=> $b['min_confirmed_matches']);

        return $tiers[0]['min_confirmed_matches'] === 0 ? $tiers : self::TIER_FALLBACK;
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

        $decoded = json_decode($value->value, true);

        return is_array($decoded) ? $decoded : null;
    }
}
