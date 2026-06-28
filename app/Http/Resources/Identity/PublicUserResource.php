<?php

declare(strict_types=1);

namespace App\Http\Resources\Identity;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Kalaanba\Modules\Identity\Application\GetPublicProfileQuery;
use Kalaanba\Modules\Identity\Domain\PublicProfile;

/**
 * Public projection for `GET /api/v1/users/{id}`. Privacy contract
 * (Identity engine doc §12 + Constitution Law 10):
 *
 *   - NEVER include phone_e164_hash, phone_e164_last4, email, email_verified_at,
 *     archived_at, last_seen_at, role (raw), area_id (raw UUID).
 *   - Surface only the area's human name (resolved server-side).
 *   - Surface only role-derived public badges (`badgesFor()` in
 *     {@see GetPublicProfileQuery}).
 *
 * @mixin PublicProfile
 */
final class PublicUserResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var PublicProfile $profile */
        $profile = $this->resource;

        return [
            'id' => $profile->id,
            'name' => $profile->name,
            'area_name' => $profile->areaName,
            'avatar_url' => $profile->avatarUrl,
            'badges' => $profile->badges,
        ];
    }
}
