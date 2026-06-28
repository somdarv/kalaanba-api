<?php

declare(strict_types=1);

namespace App\Http\Requests\Identity;

use Illuminate\Foundation\Http\FormRequest;

/**
 * PATCH /api/v1/users/me — payload validation.
 *
 * Only `name`, `area_id`, and `avatar_url` are accepted. Any other keys
 * in the body (role, phone_*, email, archive flags) are silently dropped
 * by virtue of being absent from {@see validated()} — see engine doc §8.
 *
 * Name length bounds come from admin config `users.profile.name_min`
 * / `users.profile.name_max` (engineering-standards §10 — no magic numbers).
 */
final class UpdateProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $nameMin = (int) config('users.profile.name_min', 2);
        $nameMax = (int) config('users.profile.name_max', 60);

        return [
            'name' => ['sometimes', 'string', 'min:'.$nameMin, 'max:'.$nameMax],
            'area_id' => ['sometimes', 'nullable', 'string', 'uuid'],
            'avatar_url' => ['sometimes', 'nullable', 'string', 'url', 'max:2048'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.min' => 'identity.profile.name_invalid',
            'name.max' => 'identity.profile.name_invalid',
        ];
    }
}
