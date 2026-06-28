<?php

declare(strict_types=1);

namespace Kalaanba\Modules\Identity\Domain;

use Kalaanba\Modules\Identity\Application\UpdateProfileService;

/**
 * Input DTO for {@see UpdateProfileService}.
 *
 * Every field is nullable: a PATCH may carry any subset. The service
 * applies only the fields that are non-null. `name`, `areaId`, and
 * `avatarUrl` are the ONLY mutable profile fields per the Identity
 * Engine doc §8 — role, phone, email, and archive flags are never
 * patched through this surface.
 */
final readonly class ProfileUpdate
{
    public function __construct(
        public ?string $name = null,
        public ?string $areaId = null,
        public ?string $avatarUrl = null,
    ) {}
}
