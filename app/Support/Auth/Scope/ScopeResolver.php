<?php

declare(strict_types=1);

namespace Kalaanba\Support\Auth\Scope;

use App\Models\User;

/**
 * Decides whether a user has membership in a scoped resource
 * (hub, club, competition, venue, ...).
 *
 * Engines that own scoped resources (Club, Competition, Venue, ...) bind
 * concrete resolvers in their service providers as they arrive. Until
 * then DenyAllScopeResolver answers "no" for every non-platform-admin —
 * routes that gate on scope cannot be reached by accident.
 */
interface ScopeResolver
{
    /**
     * @param  non-empty-string  $scope  One of: hub, club, competition, venue.
     * @param  non-empty-string  $scopeId  The target resource id from the route.
     */
    public function userHasScope(User $user, string $scope, string $scopeId): bool;
}
