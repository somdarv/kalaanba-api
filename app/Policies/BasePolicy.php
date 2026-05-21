<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;

/**
 * Base policy for every engine resource.
 *
 * Convention (Build Plan §0.6, engineering-standards §11):
 *   - Every resource that needs authorization gets its own
 *     {Resource}Policy in `app/Policies/<Engine>/` extending BasePolicy.
 *   - Methods correspond 1:1 to controller actions (view, list, create,
 *     update, archive, ...).
 *   - Default for unknown methods is DENY (`before()` returns null only
 *     for platform admins, who bypass).
 *   - Policies NEVER read configurable thresholds — those belong in the
 *     application service. Policies only answer "is this actor allowed
 *     to attempt this action?".
 */
abstract class BasePolicy
{
    /**
     * Platform admins (Hub / Kalaanba / Super) bypass every policy.
     * Returning true short-circuits; returning null defers to the policy
     * method (Laravel convention).
     */
    public function before(User $user, string $ability): ?bool
    {
        return $user->role->isPlatformAdmin() ? true : null;
    }
}
