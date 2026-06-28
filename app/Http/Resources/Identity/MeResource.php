<?php

declare(strict_types=1);

namespace App\Http\Resources\Identity;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Kalaanba\Modules\Identity\Domain\UserProfileSnapshot;

/**
 * Self projection for `GET /api/v1/users/me`. Carries everything the
 * authenticated user is allowed to see about themselves — full name,
 * area_id (not denormalised — frontend resolves), avatar URL, role key,
 * email + email_verified_at + phone last-4. Phone hash is never exposed.
 *
 * @mixin UserProfileSnapshot
 */
final class MeResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var UserProfileSnapshot $snapshot */
        $snapshot = $this->resource;

        return [
            'id' => $snapshot->id,
            'name' => $snapshot->name,
            'role' => $snapshot->role->value,
            'area_id' => $snapshot->areaId,
            'avatar_url' => $snapshot->avatarUrl,
            'email' => $snapshot->email,
            'email_verified_at' => $snapshot->emailVerifiedAt?->format(\DateTimeInterface::ATOM),
            'phone_e164_last4' => $snapshot->phoneE164Last4,
            'archived_at' => $snapshot->archivedAt?->format(\DateTimeInterface::ATOM),
            'last_seen_at' => $snapshot->lastSeenAt?->format(\DateTimeInterface::ATOM),
        ];
    }
}
