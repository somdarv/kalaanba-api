<?php

declare(strict_types=1);

namespace Database\Seeders;

use DateTimeImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Seed the `admin_config` table with platform defaults.
 *
 * These values are read by engines during boot and cache-layered.
 * All internal keys are snake_case, never magic numbers in domain code.
 *
 * Ref: docs/engines/rp-economy/, docs/engines/challenge/, docs/engines/season/, etc.
 */
class AdminConfigSeeder extends Seeder
{
    public function run(): void
    {
        $now = (new DateTimeImmutable('now', timezone_open('UTC')))->format('Y-m-d H:i:s');

        DB::table('admin_config')->insertOrIgnore([
            // RP Economy defaults
            [
                'key' => 'rp.win',
                'scope' => 'platform',
                'scope_id' => null,
                'value' => '10',
                'effective_from' => $now,
                'version' => 1,
                'approval_level' => 'low',
                'change_reason' => 'Initial seed: RP awarded for match win',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'key' => 'rp.draw',
                'scope' => 'platform',
                'scope_id' => null,
                'value' => '5',
                'effective_from' => $now,
                'version' => 1,
                'approval_level' => 'low',
                'change_reason' => 'Initial seed: RP awarded for draw',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'key' => 'rp.loss',
                'scope' => 'platform',
                'scope_id' => null,
                'value' => '1',
                'effective_from' => $now,
                'version' => 1,
                'approval_level' => 'low',
                'change_reason' => 'Initial seed: RP awarded for match loss',
                'created_at' => $now,
                'updated_at' => $now,
            ],

            // Challenge Engine defaults
            [
                'key' => 'challenge.min_rp_to_issue',
                'scope' => 'platform',
                'scope_id' => null,
                'value' => '50',
                'effective_from' => $now,
                'version' => 1,
                'approval_level' => 'medium',
                'change_reason' => 'Initial seed: Minimum RP required to issue a challenge',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'key' => 'challenge.response_window_hours',
                'scope' => 'platform',
                'scope_id' => null,
                'value' => '72',
                'effective_from' => $now,
                'version' => 1,
                'approval_level' => 'high',
                'change_reason' => 'Initial seed: Hours for challenge response (3 days)',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'key' => 'challenge.scheduling_window_days',
                'scope' => 'platform',
                'scope_id' => null,
                'value' => '4',
                'effective_from' => $now,
                'version' => 1,
                'approval_level' => 'high',
                'change_reason' => 'Initial seed: Days to schedule after acceptance',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'key' => 'challenge.stood_ground_cost_percent',
                'scope' => 'platform',
                'scope_id' => null,
                'value' => '0.25',
                'effective_from' => $now,
                'version' => 1,
                'approval_level' => 'high',
                'change_reason' => 'Initial seed: RP cost as % of challenged stake if draw',
                'created_at' => $now,
                'updated_at' => $now,
            ],

            // Match / Fixture defaults
            [
                'key' => 'match.default_duration_minutes',
                'scope' => 'platform',
                'scope_id' => null,
                'value' => '90',
                'effective_from' => $now,
                'version' => 1,
                'approval_level' => 'low',
                'change_reason' => 'Initial seed: Default match duration',
                'created_at' => $now,
                'updated_at' => $now,
            ],

            // Season defaults
            [
                'key' => 'season.start_month',
                'scope' => 'platform',
                'scope_id' => null,
                'value' => '4',
                'effective_from' => $now,
                'version' => 1,
                'approval_level' => 'critical',
                'change_reason' => 'Initial seed: Season starts April 1',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'key' => 'season.end_month',
                'scope' => 'platform',
                'scope_id' => null,
                'value' => '2',
                'effective_from' => $now,
                'version' => 1,
                'approval_level' => 'critical',
                'change_reason' => 'Initial seed: Season ends Feb 28/29',
                'created_at' => $now,
                'updated_at' => $now,
            ],

            // Notification & Distribution
            [
                'key' => 'notification.whatsapp_quiet_start_hour',
                'scope' => 'platform',
                'scope_id' => null,
                'value' => '22',
                'effective_from' => $now,
                'version' => 1,
                'approval_level' => 'low',
                'change_reason' => 'Initial seed: Start quiet hours (10 PM)',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'key' => 'notification.whatsapp_quiet_end_hour',
                'scope' => 'platform',
                'scope_id' => null,
                'value' => '7',
                'effective_from' => $now,
                'version' => 1,
                'approval_level' => 'low',
                'change_reason' => 'Initial seed: End quiet hours (7 AM)',
                'created_at' => $now,
                'updated_at' => $now,
            ],

            // Booking & Venue
            [
                'key' => 'booking.hold_ttl_minutes',
                'scope' => 'platform',
                'scope_id' => null,
                'value' => '30',
                'effective_from' => $now,
                'version' => 1,
                'approval_level' => 'medium',
                'change_reason' => 'Initial seed: Hold expiry on booking (30 min)',
                'created_at' => $now,
                'updated_at' => $now,
            ],

            // Moderation defaults
            [
                'key' => 'moderation.auto_hold_keywords',
                'scope' => 'platform',
                'scope_id' => null,
                'value' => '[]',
                'effective_from' => $now,
                'version' => 1,
                'approval_level' => 'high',
                'change_reason' => 'Initial seed: Empty keyword list for auto-screening',
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);
    }
}
