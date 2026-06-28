<?php

declare(strict_types=1);

namespace Kalaanba\Modules\Identity\Application;

use Kalaanba\Modules\Identity\Domain\ProfileUpdate;
use Kalaanba\Modules\Identity\Domain\UserProfileSnapshot;
use Kalaanba\Modules\Identity\IdentityServiceProvider;

/**
 * Read + partial-update port for user profile state.
 *
 * Lives in Application so the Domain layer stays framework-agnostic.
 * Infrastructure binds an Eloquent adapter in {@see IdentityServiceProvider}.
 */
interface UserProfileRepository
{
    public function find(string $userId): ?UserProfileSnapshot;

    /**
     * Apply a partial profile update and return the refreshed snapshot.
     *
     * Returns null when the user does not exist or is archived — services
     * surface this as 404 rather than 422 (engineering-standards §11).
     */
    public function applyUpdate(string $userId, ProfileUpdate $update): ?UserProfileSnapshot;
}
