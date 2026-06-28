<?php

declare(strict_types=1);

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;
use Kalaanba\Modules\Identity\Application\Lookup\LookupAccountHandler;

/**
 * POST /api/v1/auth/lookup
 *
 * Only shape validation here — the channel (phone vs email) and its
 * specific format are inferred and validated in {@see LookupAccountHandler},
 * which throws `auth.identifier_invalid` when the value is neither.
 */
final class LookupRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, string>|string>
     */
    public function rules(): array
    {
        return [
            'identifier' => ['required', 'string', 'min:3', 'max:255'],
        ];
    }
}
