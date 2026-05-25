<?php

declare(strict_types=1);

namespace Kalaanba\Modules\NotificationDistribution\Domain;

/**
 * Persistence contract for the in-app inbox. Domain-owned interface; the
 * Postgres-backed implementation lives in Infrastructure\Eloquent.
 *
 * Engineering-standards §3: Application services orchestrate via the repo;
 * only the implementation touches Eloquent.
 */
interface InboxRepository
{
    /** Persist a new inbox row. Returns the surrogate id. */
    public function insert(NewInboxItem $item): string;

    /** Fetch a single row by id. Returns null when absent. */
    public function findById(string $id): ?InboxItem;

    /**
     * List items owned by $recipientUserId, newest first. Excludes archived
     * rows. Honours the supplied cursor + filters. `$limit` is the page size
     * already clamped by the caller.
     */
    public function listForRecipient(
        int $recipientUserId,
        InboxListFilters $filters,
        ?InboxCursor $cursor,
        int $limit,
    ): InboxPage;

    /**
     * Count items for $recipientUserId whose status is still "unread"
     * (engine doc §13). Stops counting once $cap is reached — `truncated`
     * tells the caller whether the cap was hit.
     *
     * @return array{count: int, truncated: bool}
     */
    public function countUnread(int $recipientUserId, int $cap): array;

    /**
     * Transition an inbox row to `seen`. No-op when the row is already in a
     * terminal status. Returns true when a transition actually occurred.
     */
    public function markSeen(string $id, int $recipientUserId): bool;

    /**
     * Transition an inbox row to `acted_on`. No-op on terminal statuses
     * other than `seen` (seen → acted_on is allowed).
     */
    public function markActedOn(string $id, int $recipientUserId): bool;
}
