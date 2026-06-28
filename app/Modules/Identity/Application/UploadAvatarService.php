<?php

declare(strict_types=1);

namespace Kalaanba\Modules\Identity\Application;

use Illuminate\Http\UploadedFile;

/**
 * Upload an avatar via the configured {@see AvatarDriver} and return the
 * resolvable URL. The caller (Http layer) is responsible for then PATCHing
 * `users.avatar_url` via {@see UpdateProfileService} — keeping upload
 * idempotent and the PATCH simple (engine doc §8).
 */
final readonly class UploadAvatarService
{
    public function __construct(
        private AvatarDriver $driver,
    ) {}

    public function handle(string $userId, UploadedFile $file): string
    {
        return $this->driver->store($file, $userId);
    }
}
