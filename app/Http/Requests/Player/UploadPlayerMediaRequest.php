<?php

declare(strict_types=1);

namespace App\Http\Requests\Player;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Kalaanba\Modules\PlayerAffiliation\Domain\PlayerMediaKind;

/**
 * POST /api/v1/players/{playerId}/media — multipart upload validation.
 *
 * MIME allow-list and size ceiling come from admin config
 * (`player.media.allowed_mime`, `player.media.max_bytes`), never from literals
 * here — Constitution Law 2.
 *
 * `mimetypes:` re-sniffs the file's ACTUAL type with finfo rather than
 * believing the client-supplied Content-Type, which is the only version of this
 * check worth having: the header is written by whoever is uploading.
 *
 * Authorization is deliberately NOT done here. `authorize()` only asks whether
 * there is a signed-in user; whether that user owns THIS player is a §17 rule
 * and lives in {@see \Kalaanba\Modules\PlayerAffiliation\Application\UploadPlayerMedia},
 * so every future caller inherits it instead of re-implementing it.
 */
final class UploadPlayerMediaRequest extends FormRequest
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
        $maxBytes = (int) config('player.media.max_bytes', 4 * 1024 * 1024);
        $maxKilobytes = (int) ceil($maxBytes / 1024);

        /** @var list<string> $allowedMime */
        $allowedMime = (array) config('player.media.allowed_mime', [
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
            'kind' => [
                'required',
                'string',
                Rule::in(PlayerMediaKind::values()),
            ],
        ];
    }

    /**
     * Stable `engine.specific_reason` codes, not sentences. The message a
     * player reads is a configurable display string resolved at the boundary
     * (engineering-standards §10); these identify WHICH failure happened.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'file.required' => 'player.media.file_missing',
            'file.file' => 'player.media.file_missing',
            'file.max' => 'player.media.too_large',
            'file.mimetypes' => 'player.media.mime_disallowed',
            'kind.required' => 'player.media.kind_missing',
            'kind.in' => 'player.media.kind_unknown',
        ];
    }

    public function kind(): PlayerMediaKind
    {
        return PlayerMediaKind::from((string) $this->string('kind'));
    }
}
