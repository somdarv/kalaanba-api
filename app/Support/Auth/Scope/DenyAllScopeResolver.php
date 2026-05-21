<?php

declare(strict_types=1);

namespace Kalaanba\Support\Auth\Scope;

use App\Models\User;

/**
 * Default scope resolver — denies every non-platform-admin.
 *
 * Replaced (rebound in the container) by engine-specific resolvers as the
 * Club, Competition, Venue, etc. engines arrive. Until then this guarantees
 * scoped routes are inaccessible to ordinary users by accident.
 *
 * Platform admins (Hub / Kalaanba / Super) are always allowed — they bypass
 * scope membership by definition (Build Plan §0.6 admin hierarchy).
 */
final class DenyAllScopeResolver implements ScopeResolver
{
    public function userHasScope(User $user, string $scope, string $scopeId): bool
    {
        return $user->role->isPlatformAdmin();
    }
}
