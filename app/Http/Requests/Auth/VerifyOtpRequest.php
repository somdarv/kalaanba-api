<?php

declare(strict_types=1);

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

final class VerifyOtpRequest extends FormRequest
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
            'phone_e164' => ['required', 'string', 'max:16', 'regex:/^\+[1-9]\d{6,14}$/'],
            'otp' => ['required', 'string', 'regex:/^[0-9]{4,10}$/'],
            'device_name' => ['nullable', 'string', 'max:64'],
        ];
    }
}
