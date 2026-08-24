<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Kalaanba\Support\Config as KxConfig;

/**
 * Drop "side" from the amateur tier's description: "Friends, a community side,
 * a school or work team." becomes "Friends, a community, a school or work
 * team." Owner's call on the product's voice.
 *
 * WHY A MIGRATION AND NOT THE SEEDER. `AdminConfigSeeder` only inserts keys
 * that do not exist yet, deliberately, so that re-running it can never clobber
 * a value an admin has changed since. Its own docblock names the rule this
 * follows: "Changing the value of a key that already ships is a migration's
 * job, not this one's." The seeder's default was updated in the same commit so
 * a fresh environment gets the new copy on its first run; this moves the
 * environments that already hold the old one.
 *
 * WHY IT APPENDS INSTEAD OF UPDATING. `admin_config` is versioned and
 * effective-dated, one row per (key, scope, scope_id, effective_from), so past
 * values stay reconstructable and a historical read still answers correctly.
 * `Config::set()` is the write path that respects that, bumping the version
 * and invalidating the cache. Nothing here updates or deletes (Law 13).
 *
 * WHY IT CHECKS BEFORE WRITING. Display copy is a governed value with a low
 * approval level, which means an admin can legitimately have edited it. This
 * only moves a key still holding the old seeded default. Anything else is left
 * alone and reported so a human decides, which also makes a second run a
 * no-op. Same shape as `2026_08_19_000001_expand_player_positions_config`.
 */
return new class extends Migration
{
    private const KEY = 'club.tiers.descriptions';

    private const PREVIOUS = [
        'amateur' => 'Friends, a community side, a school or work team.',
        'professional' => 'A registered club or academy. We check before it goes live.',
    ];

    private const TARGET = [
        'amateur' => 'Friends, a community, a school or work team.',
        'professional' => 'A registered club or academy. We check before it goes live.',
    ];

    public function up(): void
    {
        $this->apply(self::TARGET, self::PREVIOUS, 'Drop "side" from the amateur tier description');
    }

    public function down(): void
    {
        $this->apply(self::PREVIOUS, self::TARGET, 'Restore "community side" in the amateur tier description');
    }

    /**
     * Write `$target`, but only when the key is unset or still holds
     * `$movingFrom`.
     *
     * @param  array<string, string>  $target
     * @param  array<string, string>  $movingFrom
     */
    private function apply(array $target, array $movingFrom, string $reason): void
    {
        $encoded = json_encode($target);
        $current = $this->currentValue();

        if ($current !== null && $this->sameValue($current, $encoded)) {
            return; // Already there. Re-running is a no-op.
        }

        if ($current !== null && ! $this->sameValue($current, json_encode($movingFrom))) {
            echo sprintf(
                "  SKIPPED %s: holds a value that is neither the old copy nor the new one, so it looks like a deliberate admin change. Update it in Admin Config if that is wrong.\n",
                self::KEY,
            );

            return;
        }

        KxConfig::set(
            key: self::KEY,
            value: (string) $encoded,
            scope: 'platform',
            approvalLevel: 'low',
            changeReason: $reason,
        );
    }

    /** Latest effective value, straight from the table: no cache, no fallback. */
    private function currentValue(): ?string
    {
        $row = DB::table('admin_config')
            ->where('key', self::KEY)
            ->where('scope', 'platform')
            ->whereNull('scope_id')
            ->orderByDesc('effective_from')
            ->orderByDesc('version')
            ->first(['value']);

        return $row?->value;
    }

    /** Compares decoded JSON so key order and whitespace cannot cause a false mismatch. */
    private function sameValue(string $a, string|false $b): bool
    {
        if ($b === false) {
            return false;
        }

        return json_decode($a, true) == json_decode($b, true);
    }
};
