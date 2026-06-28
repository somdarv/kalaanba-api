<?php

declare(strict_types=1);

namespace App\Http\Requests\Identity;

use Illuminate\Foundation\Http\FormRequest;

final class ConfirmPhoneChannelBindRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'phone_e164' => ['required', 'string', 'max:16', 'regex:/^\+[1-9]\d{6,14}$/'],
            'otp' => ['required', 'string', 'regex:/^[0-9]{4,10}$/'],
        ];
    }
}
