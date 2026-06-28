<?php

declare(strict_types=1);

namespace Kalaanba\Modules\Identity\Application;

use Illuminate\Validation\ValidationException;
use Kalaanba\Modules\Identity\Domain\ProfileUpdate;
use Kalaanba\Modules\Identity\Domain\UserProfileSnapshot;
use Kalaanba\Modules\Zone\Domain\GeographyReader;

/**
 * Apply a partial profile update to the authenticated user.
 *
 * Responsibility: cross-engine validation (Zone area existence) + delegate
 * persistence to the {@see UserProfileRepository} port. The FormRequest
 * has already enforced shape and config-bound length.
 *
 * Cross-engine pattern: synchronous read of the Zone read-port. No FK,
 * no cross-schema join (Constitution Law 1).
 *
 * Returns null when the user does not exist / is archived — the Http
 * layer maps that to 404. Validation failures surface as 422 with stable
 * error keys (engine doc §11).
 *
 * Engine doc: docs/engines/identity/Identity_Engine_System_Document.md §8.
 */
final readonly class UpdateProfileService
{
    public function __construct(
        private GeographyReader $geography,
        private UserProfileRepository $repository,
    ) {}

    public function handle(string $userId, ProfileUpdate $update): ?UserProfileSnapshot
    {
        if ($update->areaId !== null && $this->geography->findAreaById($update->areaId) === null) {
            throw ValidationException::withMessages([
                'area_id' => ['identity.profile.area_unknown'],
            ]);
        }

        return $this->repository->applyUpdate($userId, $update);
    }
}
