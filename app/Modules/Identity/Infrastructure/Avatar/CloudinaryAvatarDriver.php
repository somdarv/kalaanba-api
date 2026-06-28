<?php

declare(strict_types=1);

namespace Kalaanba\Modules\Identity\Infrastructure\Avatar;

use Cloudinary\Cloudinary;
use Illuminate\Http\UploadedFile;
use Kalaanba\Modules\Identity\Application\AvatarDriver;

/**
 * Production avatar driver — uploads to Cloudinary via the official PHP
 * SDK. Returns the secure delivery URL which carries on-the-fly
 * transformations for resized variants (`/w_60,h_60,c_fill,g_face/...`)
 * — no server-side resizing required.
 *
 * Credentials are env-only (`CLOUDINARY_URL`) per engineering-standards §10
 * — never in admin config. The folder prefix is admin-configurable so
 * environments can be namespaced (`kalaanba-dev/avatars` vs
 * `kalaanba-prod/avatars`).
 *
 * Engine doc: docs/engines/identity/Identity_Engine_System_Document.md §8.
 */
final readonly class CloudinaryAvatarDriver implements AvatarDriver
{
    public function __construct(
        private Cloudinary $cloudinary,
        private string $folder,
    ) {}

    public function store(UploadedFile $file, string $userId): string
    {
        $hash = hash_file('sha256', $file->getRealPath());
        $publicId = sprintf('%s/%s/%s', $this->folder, $userId, $hash);

        /** @var array{secure_url: string} $result */
        $result = $this->cloudinary
            ->uploadApi()
            ->upload($file->getRealPath(), [
                'public_id' => $publicId,
                'overwrite' => false,
                'resource_type' => 'image',
            ])
            ->getArrayCopy();

        return $result['secure_url'];
    }
}
