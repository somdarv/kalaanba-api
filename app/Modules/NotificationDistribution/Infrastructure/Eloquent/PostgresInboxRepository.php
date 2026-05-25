<?php

declare(strict_types=1);

namespace Kalaanba\Modules\NotificationDistribution\Infrastructure\Eloquent;

use Kalaanba\Modules\NotificationDistribution\Domain\InboxCategory;
use Kalaanba\Modules\NotificationDistribution\Domain\InboxCursor;
use Kalaanba\Modules\NotificationDistribution\Domain\InboxItem;
use Kalaanba\Modules\NotificationDistribution\Domain\InboxListFilters;
use Kalaanba\Modules\NotificationDistribution\Domain\InboxPage;
use Kalaanba\Modules\NotificationDistribution\Domain\InboxRepository;
use Kalaanba\Modules\NotificationDistribution\Domain\InboxStatus;
use Kalaanba\Modules\NotificationDistribution\Domain\InboxUrgency;
use Kalaanba\Modules\NotificationDistribution\Domain\NewInboxItem;
use DateTimeImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use stdClass;

/**
 * Postgres-backed inbox repository. The only place Eloquent / Query Builder
 * touches `notification_inbox`.
 */
final class PostgresInboxRepository implements InboxRepository
{
    private const TABLE = 'notification_inbox';

    public function insert(NewInboxItem $item): string
    {
        $id = (string) Str::uuid();

        DB::table(self::TABLE)->insert([
            'id' => $id,
            'recipient_user_id' => $item->recipientUserId,
            'template_key' => $item->templateKey,
            'category' => $item->category->value,
            'urgency' => $item->urgency->value,
            'status' => InboxStatus::Delivered->value,
            'title' => $item->title,
            'body' => $item->body,
            'action_url' => $item->actionUrl,
            'source_type' => $item->sourceType,
            'source_id' => $item->sourceId,
            'payload' => json_encode($item->payload, JSON_THROW_ON_ERROR),
            'expires_at' => $item->expiresAt?->format('Y-m-d H:i:sP'),
        ]);

        return $id;
    }

    public function findById(string $id): ?InboxItem
    {
        $row = DB::table(self::TABLE)
            ->whereNull('archived_at')
            ->where('id', $id)
            ->first();

        return $row === null ? null : $this->hydrate($row);
    }

    public function listForRecipient(
        int $recipientUserId,
        InboxListFilters $filters,
        ?InboxCursor $cursor,
        int $limit,
    ): InboxPage {
        $query = DB::table(self::TABLE)
            ->whereNull('archived_at')
            ->where('recipient_user_id', $recipientUserId)
            ->orderBy('created_at', 'desc')
            ->orderBy('id', 'desc')
            ->limit($limit + 1);

        if ($filters->status !== null) {
            $query->where('status', $filters->status->value);
        }
        if ($filters->category !== null) {
            $query->where('category', $filters->category->value);
        }
        if ($cursor !== null) {
            $createdAt = $cursor->createdAtIso;
            $id = $cursor->id;
            $query->where(function ($q) use ($createdAt, $id): void {
                $q->where('created_at', '<', $createdAt)
                    ->orWhere(function ($inner) use ($createdAt, $id): void {
                        $inner->where('created_at', '=', $createdAt)
                            ->where('id', '<', $id);
                    });
            });
        }

        $rows = $query->get();
        $hasMore = $rows->count() > $limit;
        $page = $rows->take($limit);

        $items = $page->map(fn (stdClass $row): InboxItem => $this->hydrate($row))->all();

        $nextCursor = null;
        if ($hasMore && $items !== []) {
            $last = end($items);
            $nextCursor = new InboxCursor(
                $last->createdAt->format('Y-m-d\TH:i:s.uP'),
                $last->id,
            );
        }

        return new InboxPage(array_values($items), $nextCursor);
    }

    public function countUnread(int $recipientUserId, int $cap): array
    {
        // LIMIT + 1 lets us know whether the true count exceeds the cap
        // without scanning the whole inbox.
        $rows = DB::table(self::TABLE)
            ->whereNull('archived_at')
            ->where('recipient_user_id', $recipientUserId)
            ->whereNotIn('status', [
                InboxStatus::Seen->value,
                InboxStatus::ActedOn->value,
                InboxStatus::Expired->value,
                InboxStatus::Cancelled->value,
            ])
            ->limit($cap + 1)
            ->count('id');

        $truncated = $rows > $cap;

        return [
            'count' => $truncated ? $cap : (int) $rows,
            'truncated' => $truncated,
        ];
    }

    public function markSeen(string $id, int $recipientUserId): bool
    {
        $affected = DB::table(self::TABLE)
            ->where('id', $id)
            ->where('recipient_user_id', $recipientUserId)
            ->whereNull('archived_at')
            ->whereNotIn('status', [
                InboxStatus::Seen->value,
                InboxStatus::ActedOn->value,
                InboxStatus::Expired->value,
                InboxStatus::Cancelled->value,
            ])
            ->update([
                'status' => InboxStatus::Seen->value,
                'seen_at' => DB::raw('now()'),
            ]);

        return $affected > 0;
    }

    public function markActedOn(string $id, int $recipientUserId): bool
    {
        $affected = DB::table(self::TABLE)
            ->where('id', $id)
            ->where('recipient_user_id', $recipientUserId)
            ->whereNull('archived_at')
            ->whereNotIn('status', [
                InboxStatus::ActedOn->value,
                InboxStatus::Expired->value,
                InboxStatus::Cancelled->value,
            ])
            ->update([
                'status' => InboxStatus::ActedOn->value,
                'acted_on_at' => DB::raw('now()'),
            ]);

        return $affected > 0;
    }

    private function hydrate(stdClass $row): InboxItem
    {
        $payload = $row->payload === null ? [] : (array) json_decode((string) $row->payload, true);

        return new InboxItem(
            id: (string) $row->id,
            recipientUserId: (int) $row->recipient_user_id,
            templateKey: (string) $row->template_key,
            category: InboxCategory::from((string) $row->category),
            urgency: InboxUrgency::from((string) $row->urgency),
            status: InboxStatus::from((string) $row->status),
            title: (string) $row->title,
            body: (string) $row->body,
            actionUrl: $row->action_url !== null ? (string) $row->action_url : null,
            sourceType: (string) $row->source_type,
            sourceId: $row->source_id !== null ? (string) $row->source_id : null,
            payload: $payload,
            createdAt: new DateTimeImmutable((string) $row->created_at),
            seenAt: $row->seen_at !== null ? new DateTimeImmutable((string) $row->seen_at) : null,
            actedOnAt: $row->acted_on_at !== null ? new DateTimeImmutable((string) $row->acted_on_at) : null,
            expiresAt: $row->expires_at !== null ? new DateTimeImmutable((string) $row->expires_at) : null,
        );
    }
}
