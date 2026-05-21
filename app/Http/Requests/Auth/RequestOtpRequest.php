<?php

declare(strict_types=1);

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

final class RequestOtpRequest extends FormRequest
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
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'phone_e164.regex' => 'phone_e164 must be E.164-formatted (e.g. +233244123456).',
        ];
    }
}
