<?php

declare(strict_types=1);

namespace App\Http\Requests\Club;

use Illuminate\Foundation\Http\FormRequest;
use Kalaanba\Modules\Club\Application\UploadClubCrest;
use Kalaanba\Support\Config as KxConfig;
use Throwable;

/**
 * POST /api/v1/clubs/{clubId}/crest — multipart image upload.
 *
 * Shape and safety only: the size ceiling and the MIME allow-list. Whether the
 * caller may touch this club is a domain rule and lives in
 * {@see UploadClubCrest}.
 *
 * Both limits are config, not literals, because "how big is too big" is a
 * question about Ghanaian mobile data and the answer will change (Law 2).
 */
final class UploadClubCrestRequest extends FormRequest
{
    private const MAX_KILOBYTES_FALLBACK = 5120;

    private const MIME_FALLBACK = ['jpg', 'jpeg', 'png', 'webp'];

    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, array<int, mixed>|string>
     */
    public function rules(): array
    {
        return [
            'file' => [
                'required',
                'file',
                'image',
                'mimes:'.implode(',', $this->allowedMimes()),
                'max:'.$this->maxKilobytes(),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'file.required' => 'club.crest_required',
            'file.max' => 'club.crest_too_large',
            'file.mimes' => 'club.crest_type_unsupported',
            'file.image' => 'club.crest_type_unsupported',
        ];
    }

    private function maxKilobytes(): int
    {
        $value = $this->intConfig('club.media.max_kilobytes');

        return $value > 0 ? $value : self::MAX_KILOBYTES_FALLBACK;
    }

    /**
     * @return list<string>
     */
    private function allowedMimes(): array
    {
        try {
            $value = KxConfig::get('club.media.allowed_extensions');
            if ($value === null) {
                return self::MIME_FALLBACK;
            }
            $decoded = json_decode((string) $value->value, true);

            return is_array($decoded) && $decoded !== []
                ? array_values(array_filter($decoded, 'is_string'))
                : self::MIME_FALLBACK;
        } catch (Throwable) {
            return self::MIME_FALLBACK;
        }
    }

    private function intConfig(string $key): int
    {
        try {
            $value = KxConfig::get($key);

            return $value === null || ! is_numeric($value->value) ? 0 : (int) $value->value;
        } catch (Throwable) {
            return 0;
        }
    }
}
