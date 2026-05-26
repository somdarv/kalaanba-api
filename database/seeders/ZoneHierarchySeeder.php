<?php

declare(strict_types=1);

namespace Database\Seeders;

use DateTimeImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Bootstrap Zone-engine hierarchy:
 *   Ghana → Northern Region → Tamale City Hub → (Zone: Tamale Central)
 *                                                → (Area: Aboabo)
 *
 * Deterministic UUIDs so re-running idempotent. Engine doc §2.
 */
class ZoneHierarchySeeder extends Seeder
{
    public function run(): void
    {
        $now = (new DateTimeImmutable('now', timezone_open('UTC')))->format('Y-m-d H:i:s');

        $countryId = '8c2f9d0a-2c5b-4e3e-9c1e-6a3b1a0e1001';
        $regionId = '8c2f9d0a-2c5b-4e3e-9c1e-6a3b1a0e1002';
        $cityHubId = '8c2f9d0a-2c5b-4e3e-9c1e-6a3b1a0e1003';
        $zoneId = '8c2f9d0a-2c5b-4e3e-9c1e-6a3b1a0e1004';
        $areaId = '8c2f9d0a-2c5b-4e3e-9c1e-6a3b1a0e1005';

        DB::table('countries')->insertOrIgnore([
            'id' => $countryId,
            'code' => 'GH',
            'name' => 'Ghana',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        DB::table('regions')->insertOrIgnore([
            'id' => $regionId,
            'country_id' => $countryId,
            'code' => 'NR',
            'name' => 'Northern Region',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        DB::table('city_hubs')->insertOrIgnore([
            'id' => $cityHubId,
            'region_id' => $regionId,
            'code' => 'TML',
            'name' => 'Tamale',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        DB::table('zones')->insertOrIgnore([
            'id' => $zoneId,
            'city_hub_id' => $cityHubId,
            'kind' => 'zone',
            'code' => 'tml-central',
            'name' => 'Tamale Central',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        DB::table('areas')->insertOrIgnore([
            'id' => $areaId,
            'zone_id' => $zoneId,
            'code' => 'aboabo',
            'name' => 'Aboabo',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }
}
