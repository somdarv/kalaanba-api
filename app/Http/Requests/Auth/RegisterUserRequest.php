<?php

declare(strict_types=1);

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;
use Kalaanba\Support\Config as KxConfig;

/**
 * POST /api/v1/auth/registration — discriminated by `channel`.
 *
 * Cross-channel mutual exclusion is enforced via Application-layer
 * validation (the handler) rather than complex `required_if` chains so
 * the error messages stay aligned with the contract's `oneOf` shape.
 *
 * `name` length boundary is read from `profile.display_name.max` so it
 * matches the rule the rest of the engine uses (engine doc §8).
 */
final class RegisterUserRequest extends FormRequest
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
        $nameMax = $this->readConfigInt('profile.display_name.max', 80);

        return [
            'channel' => ['required', 'string', 'in:phone,email'],
            'name' => ['required', 'string', 'min:1', "max:{$nameMax}"],
            // Optional at self-signup — area is chosen later on the profile
            // screen (no public area picker exists yet). Validated against
            // Zone only when present (RegisterUserHandler).
            'area_id' => ['nullable', 'uuid'],
            'registered_via' => ['nullable', 'string', 'in:self'],

            // Phone path.
            'phone_e164' => ['nullable', 'string', 'max:16', 'regex:/^\+[1-9]\d{6,14}$/'],
            'otp' => ['nullable', 'string', 'regex:/^[0-9]{4,10}$/'],
            'device_name' => ['nullable', 'string', 'max:64'],

            // Email path.
            'email' => ['nullable', 'email:rfc,strict', 'max:255'],
            'password' => ['nullable', 'string', 'min:1', 'max:255'],
        ];
    }

    private function readConfigInt(string $key, int $fallback): int
    {
        try {
            $value = KxConfig::get($key);

            return $value === null ? $fallback : (int) $value->value;
        } catch (\Throwable) {
            return $fallback;
        }
    }
}
