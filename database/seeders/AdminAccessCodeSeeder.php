<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Admin\AdminAccessCode;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Seeds the Users-section admin access code.
 *
 * Pre-alpha default is `023050` (set per product owner). Stored ONLY as a
 * bcrypt hash — never plaintext. Override via the ADMIN_USERS_ACCESS_CODE env
 * var. Idempotent: updates the hash in place on re-seed.
 */
class AdminAccessCodeSeeder extends Seeder
{
    public function run(): void
    {
        $code = (string) env('ADMIN_USERS_ACCESS_CODE', '023050');

        AdminAccessCode::query()->updateOrCreate(
            ['label' => AdminAccessCode::USERS_SECTION],
            ['code_hash' => Hash::make($code)],
        );
    }
}
