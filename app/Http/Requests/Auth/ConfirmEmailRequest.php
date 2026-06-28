<?php

declare(strict_types=1);

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

/**
 * POST /api/v1/auth/email/verify
 *
 * Confirms an email verification token. Anonymous (registration confirm)
 * AND authenticated (bind_email confirm) traffic both hit this endpoint;
 * the handler distinguishes via the token's stored `purpose`.
 */
final class ConfirmEmailRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'token' => ['required', 'string', 'min:32', 'max:128'],
            'device_name' => ['nullable', 'string', 'max:64'],
        ];
    }
}
