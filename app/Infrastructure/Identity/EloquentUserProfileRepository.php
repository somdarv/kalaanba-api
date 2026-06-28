<?php

declare(strict_types=1);

namespace App\Infrastructure\Identity;

use App\Models\User;
use Kalaanba\Modules\Identity\Application\UserProfileRepository;
use Kalaanba\Modules\Identity\Domain\ProfileUpdate;
use Kalaanba\Modules\Identity\Domain\UserProfileSnapshot;

/**
 * Eloquent adapter implementing the Identity engine's {@see UserProfileRepository}
 * port. Lives in the App namespace (not the engine module) because it bridges
 * the global App\Models\User — engine modules are forbidden by architecture
 * test from depending on App\Models directly.
 *
 * Active rows only: archived users are treated as absent (returns null).
 */
final readonly class EloquentUserProfileRepository implements UserProfileRepository
{
    public function find(string $userId): ?UserProfileSnapshot
    {
        $user = User::query()
            ->whereKey($userId)
            ->whereNull('archived_at')
            ->first();

        return $user === null ? null : $this->snapshot($user);
    }

    public function applyUpdate(string $userId, ProfileUpdate $update): ?UserProfileSnapshot
    {
        $user = User::query()
            ->whereKey($userId)
            ->whereNull('archived_at')
            ->first();

        if ($user === null) {
            return null;
        }

        if ($update->name !== null) {
            $user->name = $update->name;
        }
        if ($update->areaId !== null) {
            $user->area_id = $update->areaId;
        }
        if ($update->avatarUrl !== null) {
            $user->avatar_url = $update->avatarUrl;
        }

        $user->save();

        return $this->snapshot($user->refresh());
    }

    private function snapshot(User $user): UserProfileSnapshot
    {
        return new UserProfileSnapshot(
            id: (string) $user->getKey(),
            name: $user->name,
            role: $user->role,
            areaId: $user->area_id,
            avatarUrl: $user->avatar_url,
            email: $user->email,
            emailVerifiedAt: $user->email_verified_at?->toDateTimeImmutable(),
            phoneE164Last4: $user->phone_e164_last4,
            archivedAt: $user->archived_at?->toDateTimeImmutable(),
            lastSeenAt: $user->last_seen_at?->toDateTimeImmutable(),
        );
    }
}
