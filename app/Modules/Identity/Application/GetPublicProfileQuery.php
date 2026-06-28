<?php

declare(strict_types=1);

namespace Kalaanba\Modules\Identity\Application;

use Kalaanba\Modules\Identity\Domain\PublicProfile;
use Kalaanba\Modules\Zone\Domain\GeographyReader;
use Kalaanba\Support\Auth\Role;

/**
 * Read-side query that returns the public projection of a user.
 *
 * Returns `null` when the user is archived or does not exist — callers
 * surface this as 404 so existence is not leaked (engineering-standards §11).
 *
 * Cross-engine read of Zone\Domain to denormalise `area_name`. No FK; the
 * area may have been renamed since the user picked it.
 *
 * Engine doc: docs/engines/identity/Identity_Engine_System_Document.md §12.
 */
final readonly class GetPublicProfileQuery
{
    public function __construct(
        private UserProfileRepository $repository,
        private GeographyReader $geography,
    ) {}

    public function handle(string $userId): ?PublicProfile
    {
        $snapshot = $this->repository->find($userId);
        if ($snapshot === null) {
            return null;
        }

        $areaName = null;
        if ($snapshot->areaId !== null) {
            $areaName = $this->geography->findAreaById($snapshot->areaId)?->name;
        }

        return new PublicProfile(
            id: $snapshot->id,
            name: $snapshot->name,
            areaName: $areaName,
            avatarUrl: $snapshot->avatarUrl,
            badges: $this->badgesFor($snapshot->role),
        );
    }

    /**
     * Role-derived public badges. Stable keys (snake_case), never display
     * strings. The frontend maps badge keys to localised labels.
     *
     * @return list<string>
     */
    private function badgesFor(Role $role): array
    {
        return match ($role) {
            Role::Referee => ['referee'],
            Role::FacilityManager => ['facility_manager'],
            Role::HubAdmin, Role::KalaanbaAdmin, Role::SuperAdmin => ['admin'],
            default => [],
        };
    }
}
