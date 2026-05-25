<?php

declare(strict_types=1);

namespace App\Http\Resources\Notifications;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Kalaanba\Modules\NotificationDistribution\Domain\InboxItem;

/**
 * Maps an InboxItem DTO to the JSON shape defined by
 * contracts/api/notification-distribution/get-me-notifications.v1.yaml.
 *
 * @property InboxItem $resource
 */
final class InboxItemResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var InboxItem $item */
        $item = $this->resource;

        return [
            'id' => $item->id,
            'template_key' => $item->templateKey,
            'category' => $item->category->value,
            'urgency' => $item->urgency->value,
            'status' => $item->status->value,
            'title' => $item->title,
            'body' => $item->body,
            'action_url' => $item->actionUrl,
            'source_type' => $item->sourceType,
            'source_id' => $item->sourceId,
            'created_at' => $item->createdAt->format(DATE_ATOM),
            'seen_at' => $item->seenAt?->format(DATE_ATOM),
            'acted_on_at' => $item->actedOnAt?->format(DATE_ATOM),
            'expires_at' => $item->expiresAt?->format(DATE_ATOM),
        ];
    }
}
