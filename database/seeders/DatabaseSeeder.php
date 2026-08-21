<?php

namespace Database\Seeders;

use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // User::factory(10)->create();

        User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);

        // NOTE: GhanaGeographySeeder is deliberately NOT called here.
        // tests/TestCase.php sets `$seed = true`, so DatabaseSeeder runs before
        // EVERY test, and putting the Ghana geography in it gave all of them 13
        // hubs and 55 areas. Six tests that assert what the picker returns
        // broke immediately, and every future one would have carried the noise.
        //
        // Reference geography is an operational seed, run once per environment:
        //     php artisan db:seed --class=GhanaGeographySeeder
        // It is idempotent, so re-running is a no-op.
        $this->call(AdminConfigSeeder::class);
        $this->call(SuperAdminSeeder::class);
        $this->call(AdminAccessCodeSeeder::class);
    }
}
