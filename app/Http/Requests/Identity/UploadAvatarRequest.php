<?php

declare(strict_types=1);

namespace App\Http\Requests\Identity;

use Illuminate\Foundation\Http\FormRequest;

/**
 * POST /api/v1/users/me/avatar — multipart upload validation.
 *
 * MIME allow-list and max size come from admin config — see
 * `users.avatar.allowed_mime` and `users.avatar.max_bytes` (engineering-standards §10).
 *
 * Laravel re-sniffs the file's actual MIME server-side via `mimetypes:`
 * (uses finfo, not the client-supplied Content-Type) — security review §7.
 */
final class UploadAvatarRequest extends FormRequest
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
        $maxBytes = (int) config('users.avatar.max_bytes', 2 * 1024 * 1024);
        $maxKilobytes = (int) ceil($maxBytes / 1024);

        /** @var list<string> $allowedMime */
        $allowedMime = (array) config('users.avatar.allowed_mime', [
            'image/jpeg',
            'image/png',
            'image/webp',
        ]);

        return [
            'file' => [
                'required',
                'file',
                'mimetypes:'.implode(',', $allowedMime),
                'max:'.$maxKilobytes,
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'file.max' => 'identity.avatar.too_large',
            'file.mimetypes' => 'identity.avatar.mime_disallowed',
            'file.required' => 'identity.avatar.file_missing',
            'file.file' => 'identity.avatar.file_missing',
        ];
    }
}
