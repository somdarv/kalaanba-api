<?php

declare(strict_types=1);

namespace Kalaanba\Modules\Identity\Domain;

use DateTimeImmutable;
use Kalaanba\Modules\Identity\Application\UserProfileRepository;
use Kalaanba\Support\Auth\Role;

/**
 * Framework-agnostic snapshot of a user's profile state, returned by
 * {@see UserProfileRepository}.
 *
 * The Identity engine never touches the Eloquent User model directly —
 * that dependency is forbidden by the engine isolation architecture test
 * (engine modules MUST NOT depend on App\Models). The repository adapter
 * in Infrastructure does the loading and mapping.
 */
final readonly class UserProfileSnapshot
{
    public function __construct(
        public string $id,
        public string $name,
        public Role $role,
        public ?string $areaId,
        public ?string $avatarUrl,
        // Nullable: a phone-only user has no email (Identity §2/§8 — exactly one
        // channel is required, both are optional individually).
        public ?string $email,
        public ?DateTimeImmutable $emailVerifiedAt,
        public ?string $phoneE164Last4,
        public ?DateTimeImmutable $archivedAt,
        public ?DateTimeImmutable $lastSeenAt,
    ) {}

    public function isArchived(): bool
    {
        return $this->archivedAt !== null;
    }
}
