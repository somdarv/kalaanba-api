<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Kalaanba\Support\Config as KxConfig;

/**
 * Expand `player.positions` from four coarse keys to the thirteen a player
 * names on a pitch, and add the two display maps the setup picker needs.
 *
 * WHY A MIGRATION AND NOT THE SEEDER. `AdminConfigSeeder` writes with
 * `insertOrIgnore`, which is right for a seeder: re-running it must never
 * clobber a value an admin has since changed. That also means it can only ever
 * ADD keys, so it cannot move an environment that already holds the old
 * four-key row. Migrations run once per environment on deploy and are the
 * sanctioned home for a data change (engineering-standards §6: backfills are
 * separate migrations so they can be re-run independently).
 *
 * WHY IT APPENDS INSTEAD OF UPDATING. `admin_config` is versioned and
 * effective-dated: one row per (key, scope, scope_id, effective_from), so past
 * values stay reconstructable and `Config::get(..., at: $then)` still answers
 * correctly for a historical read. `Config::set()` is the write path that
 * respects that, bumping the version and invalidating the cache. Nothing here
 * updates or deletes a row (Constitution Law 13).
 *
 * WHY IT CHECKS BEFORE WRITING. If an admin has deliberately customised
 * `player.positions` in production, that is a governed decision and a deploy
 * has no business overwriting it. This only moves a key that still holds the
 * old seeded default, or has no value at all. Anything else is left exactly as
 * it is and reported, so a human decides. That also makes the migration safe
 * to re-run: a second pass finds the target value already in place and writes
 * nothing.
 */
return new class extends Migration
{
    /** The value this migration is moving environments off. */
    private const PREVIOUS_POSITIONS = ['goalkeeper', 'defender', 'midfielder', 'forward'];

    private const PREVIOUS_LABELS = [
        'goalkeeper' => 'Goalkeeper',
        'defender' => 'Defender',
        'midfielder' => 'Midfielder',
        'forward' => 'Forward',
    ];

    private const POSITIONS = [
        'goalkeeper',
        'left_back', 'centre_back', 'right_back',
        'defensive_midfielder',
        'left_midfielder', 'centre_midfielder', 'right_midfielder',
        'attacking_midfielder',
        'left_winger', 'right_winger',
        'second_striker', 'striker',
    ];

    private const LABELS = [
        'goalkeeper' => 'Goalkeeper',
        'left_back' => 'Left Back',
        'centre_back' => 'Centre Back',
        'right_back' => 'Right Back',
        'defensive_midfielder' => 'Defensive Midfielder',
        'left_midfielder' => 'Left Midfielder',
        'centre_midfielder' => 'Centre Midfielder',
        'right_midfielder' => 'Right Midfielder',
        'attacking_midfielder' => 'Attacking Midfielder',
        'left_winger' => 'Left Winger',
        'right_winger' => 'Right Winger',
        'second_striker' => 'Second Striker',
        'striker' => 'Striker',
        // Retired keys. They are NOT in `player.positions`, so nobody can
        // choose them any more, but a player who picked one before
        // 2026-08-19 still has it stored on their row. Labels are a lookup,
        // not an option set: keeping these is what stops an existing profile
        // rendering the raw key "midfielder" at people. Deprecate, don't
        // delete (engineering-standards §7).
        'defender' => 'Defender',
        'midfielder' => 'Midfielder',
        'forward' => 'Forward',
    ];

    private const ABBREVIATIONS = [
        'goalkeeper' => 'GK',
        'left_back' => 'LB',
        'centre_back' => 'CB',
        'right_back' => 'RB',
        'defensive_midfielder' => 'DM',
        'left_midfielder' => 'LM',
        'centre_midfielder' => 'CM',
        'right_midfielder' => 'RM',
        'attacking_midfielder' => 'AM',
        'left_winger' => 'LW',
        'right_winger' => 'RW',
        'second_striker' => 'SS',
        'striker' => 'ST',
        // Retired keys, kept for the same reason as the labels above.
        'defender' => 'DF',
        'midfielder' => 'MF',
        'forward' => 'FW',
    ];

    private const DESCRIPTIONS = [
        'goalkeeper' => 'You keep the ball out of the net.',
        'left_back' => 'You defend the left side and help the attack.',
        'centre_back' => 'You defend the middle and win headers.',
        'right_back' => 'You defend the right side and help the attack.',
        'defensive_midfielder' => 'You sit in front of the defence and win the ball.',
        'left_midfielder' => 'You run the left side, back and forward.',
        'centre_midfielder' => 'You run the middle and set the pace.',
        'right_midfielder' => 'You run the right side, back and forward.',
        'attacking_midfielder' => 'You create the chances.',
        'left_winger' => 'You take players on down the left.',
        'right_winger' => 'You take players on down the right.',
        'second_striker' => 'You play just behind the striker.',
        'striker' => 'You score the goals.',
    ];

    private const REASON = 'Expand primary positions to the thirteen the setup pitch picker places';

    public function up(): void
    {
        $this->apply('player.positions', self::POSITIONS, self::PREVIOUS_POSITIONS, 'medium');
        $this->apply('player.positions.labels', self::LABELS, self::PREVIOUS_LABELS, 'low');
        $this->apply('player.positions.abbreviations', self::ABBREVIATIONS, null, 'low');
        $this->apply('player.positions.descriptions', self::DESCRIPTIONS, null, 'low');
    }

    /**
     * Reverting appends the previous value as a further version rather than
     * deleting the rows this added. The forward change stays in the history,
     * which is the whole point of an effective-dated registry. The two keys
     * that did not exist before are left in place: removing a key an admin can
     * now see and edit is more destructive than leaving an unread one behind.
     */
    public function down(): void
    {
        $this->apply('player.positions', self::PREVIOUS_POSITIONS, self::POSITIONS, 'medium', 'Revert to the four coarse positions');
        $this->apply('player.positions.labels', self::PREVIOUS_LABELS, self::LABELS, 'low', 'Revert to the four coarse position labels');
    }

    /**
     * Write `$target` to `$key`, but only when the key is unset or still holds
     * `$movingFrom`. Pass `null` for `$movingFrom` to mean "only write when the
     * key has no value at all".
     *
     * @param  array<mixed>  $target
     * @param  array<mixed>|null  $movingFrom
     */
    private function apply(
        string $key,
        array $target,
        ?array $movingFrom,
        string $approvalLevel,
        ?string $reason = null,
    ): void {
        $encodedTarget = json_encode($target);
        $current = $this->currentValue($key);

        if ($current === $encodedTarget) {
            return; // Already there. Re-running this migration is a no-op.
        }

        if ($current !== null) {
            $isStaleDefault = $movingFrom !== null
                && $this->sameValue($current, json_encode($movingFrom));

            if (! $isStaleDefault) {
                // Somebody chose this on purpose. Leave it and say so.
                echo sprintf(
                    "  SKIPPED %s: holds a value that is neither the old default nor the new one, so it looks like a deliberate admin change. Update it in Admin Config if that is wrong.\n",
                    $key,
                );

                return;
            }
        }

        KxConfig::set(
            key: $key,
            value: (string) $encodedTarget,
            scope: 'platform',
            approvalLevel: $approvalLevel,
            changeReason: $reason ?? self::REASON,
        );
    }

    /** Latest effective value, straight from the table: no cache, no fallback. */
    private function currentValue(string $key): ?string
    {
        $row = DB::table('admin_config')
            ->where('key', $key)
            ->where('scope', 'platform')
            ->whereNull('scope_id')
            ->orderByDesc('effective_from')
            ->orderByDesc('version')
            ->first(['value']);

        return $row?->value;
    }

    /** Compares decoded JSON so key order and whitespace do not cause a false mismatch. */
    private function sameValue(string $a, string|false $b): bool
    {
        if ($b === false) {
            return false;
        }

        return json_decode($a, true) == json_decode($b, true);
    }
};
