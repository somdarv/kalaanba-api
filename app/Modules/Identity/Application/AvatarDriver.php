<?php

declare(strict_types=1);

namespace Kalaanba\Modules\Identity\Application;

use Illuminate\Http\UploadedFile;

/**
 * Driver contract for persisting user avatar uploads.
 *
 * Implementations (in Identity\Infrastructure\Avatar):
 *  - LocalAvatarDriver — writes to storage/app/public/avatars (dev/CI default).
 *  - CloudinaryAvatarDriver — uploads via cloudinary/cloudinary_php SDK (alpha+ production).
 *
 * Selection is config-driven through `config('users.avatar.driver')`
 * (see Identity\Infrastructure\Avatar\AvatarDriverFactory).
 *
 * Engine doc: docs/engines/identity/Identity_Engine_System_Document.md §8.
 */
interface AvatarDriver
{
    /**
     * Persist an avatar upload and return a resolvable URL to store on
     * users.avatar_url. The URL must be publicly fetchable (or CDN-fronted)
     * since it is rendered on the public `GET /users/{id}` projection.
     *
     * The implementation owns content-addressing (hash the bytes to defeat
     * duplicate uploads) and any provider-specific transformation defaults.
     */
    public function store(UploadedFile $file, string $userId): string;
}
