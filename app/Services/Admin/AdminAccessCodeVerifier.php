<?php

declare(strict_types=1);

namespace App\Services\Admin;

use App\Models\Admin\AdminAccessCode;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;

/**
 * Verifies the admin access code that gates destructive Users-section actions
 * (ADR-0005). The plaintext code is only ever compared against a stored bcrypt
 * hash — it is never read back or logged.
 */
final class AdminAccessCodeVerifier
{
    public function verify(string $code, string $label = AdminAccessCode::USERS_SECTION): bool
    {
        $row = AdminAccessCode::query()->where('label', $label)->first();

        if ($row === null || ! Hash::check($code, $row->code_hash)) {
            return false;
        }

        $row->forceFill(['last_used_at' => Carbon::now('UTC')])->save();

        return true;
    }
}
