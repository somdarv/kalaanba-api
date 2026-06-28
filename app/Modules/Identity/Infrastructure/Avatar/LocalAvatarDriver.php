<?php

declare(strict_types=1);

namespace Kalaanba\Modules\Identity\Infrastructure\Avatar;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Kalaanba\Modules\Identity\Application\AvatarDriver;

/**
 * Writes avatar uploads to the `public` filesystem disk under
 * `avatars/{userId}/{contentHash}.{ext}`. Content-addressed path
 * defeats duplicate uploads and avoids enumeration.
 *
 * Used in dev, test, and CI environments by default. The on-disk
 * artefact is served via Laravel's storage symlink
 * (`php artisan storage:link`).
 *
 * Engine doc: docs/engines/identity/Identity_Engine_System_Document.md §8.
 */
final readonly class LocalAvatarDriver implements AvatarDriver
{
    public function __construct(private string $disk = 'public') {}

    public function store(UploadedFile $file, string $userId): string
    {
        $extension = strtolower($file->getClientOriginalExtension() !== ''
            ? $file->getClientOriginalExtension()
            : ($file->guessExtension() ?? 'bin'));

        $hash = hash_file('sha256', $file->getRealPath());
        $path = sprintf('avatars/%s/%s.%s', $userId, $hash, $extension);

        Storage::disk($this->disk)->putFileAs(
            dirname($path),
            $file,
            basename($path),
        );

        return Storage::disk($this->disk)->url($path);
    }
}
