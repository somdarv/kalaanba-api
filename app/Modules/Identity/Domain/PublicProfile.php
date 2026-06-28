<?php

declare(strict_types=1);

namespace Kalaanba\Modules\Identity\Domain;

/**
 * Read-model returned by GetPublicProfileQuery — the strictly-public
 * projection of a user (see Identity engine doc §12 Privacy Contract).
 *
 * Excludes phone/email/archive fields. Resolved `area_name` is denormalised
 * here so the Http resource does not need a second Zone lookup.
 *
 * `badges` is a role-derived attention list (e.g. ["referee"], ["admin"]).
 */
final readonly class PublicProfile
{
    /**
     * @param  list<string>  $badges
     */
    public function __construct(
        public string $id,
        public string $name,
        public ?string $areaName,
        public ?string $avatarUrl,
        public array $badges,
    ) {}
}
