<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Demo clubs for alpha/manual testing of the "clubs near you" finder + join
 * flow (WP-20260702, WP-C1/C2). Seeds a couple of clubs into every existing
 * Area so whichever area a tester picks shows something to join. Idempotent —
 * skips areas that already have clubs. NOT for production.
 */
final class ClubDemoSeeder extends Seeder
{
    private const DEMO_OWNER_ID = '8c2f9d0a-2c5b-4e3e-9c1e-6a3b1a0e0d01';

    public function run(): void
    {
        $now = Carbon::now();

        /** @var array<int, object{id: string, zone_id: string}> $areas */
        $areas = DB::table('areas')->select('id')->get();

        foreach ($areas as $area) {
            $areaId = (string) $area->id;
            if (DB::table('clubs')->where('area_id', $areaId)->exists()) {
                continue;
            }

            $cityHubId = (string) DB::table('zones')
                ->join('areas', 'areas.zone_id', '=', 'zones.id')
                ->where('areas.id', $areaId)
                ->value('zones.city_hub_id');

            if ($cityHubId === '') {
                continue;
            }

            foreach ([
                ['name' => 'Bantama Boys', 'type' => 'community'],
                ['name' => 'Aboabo United', 'type' => 'informal'],
            ] as $demo) {
                $clubId = (string) Str::uuid();
                DB::table('clubs')->insert([
                    'id' => $clubId,
                    'name' => $demo['name'],
                    'club_type' => $demo['type'],
                    'city_hub_id' => $cityHubId,
                    'area_id' => $areaId,
                    'crest_url' => null,
                    'maturity_level' => 'informal',
                    'created_by_user_id' => self::DEMO_OWNER_ID,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
                DB::table('club_memberships')->insert([
                    'id' => (string) Str::uuid(),
                    'club_id' => $clubId,
                    'user_id' => self::DEMO_OWNER_ID,
                    'role' => 'owner',
                    'state' => 'active',
                    'created_at' => $now,
                ]);
            }
        }
    }
}
