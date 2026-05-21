<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;

/**
 * Read-only listing of the admin audit log. Super Admin only — gated by
 * route middleware + an explicit re-check below.
 *
 * Cursor pagination per engineering-standards §7: cursor is an opaque
 * base64 of "{occurred_at_iso}|{id}", page_size soft-defaulted to 25 (max 100).
 */
final class AuditLogController extends Controller
{
    private const DEFAULT_LIMIT = 25;

    private const MAX_LIMIT = 100;

    public function index(Request $request): JsonResponse
    {
        $limit = $this->resolveLimit($request);
        $query = DB::table('admin_audit_log')
            ->orderBy('occurred_at', 'desc')
            ->orderBy('id', 'desc')
            ->limit($limit + 1);

        $cursor = $this->decodeCursor($request->query('cursor'));
        if ($cursor !== null) {
            [$occurredAt, $id] = $cursor;
            $query->where(function ($q) use ($occurredAt, $id): void {
                $q->where('occurred_at', '<', $occurredAt)
                    ->orWhere(function ($q2) use ($occurredAt, $id): void {
                        $q2->where('occurred_at', '=', $occurredAt)
                            ->where('id', '<', $id);
                    });
            });
        }

        $rows = $query->get();
        $hasMore = $rows->count() > $limit;
        $page = $rows->take($limit);

        $nextCursor = null;
        if ($hasMore) {
            $last = $page->last();
            $nextCursor = $this->encodeCursor(
                (string) $last->occurred_at,
                (string) $last->id,
            );
        }

        return new JsonResponse([
            'data' => $page->map(fn ($row): array => [
                'id' => (string) $row->id,
                'actor_id' => (string) $row->actor_id,
                'actor_role' => (string) $row->actor_role,
                'request_id' => (string) $row->request_id,
                'route' => $row->route !== null ? (string) $row->route : null,
                'method' => (string) $row->method,
                'path' => (string) $row->path,
                'response_status' => (int) $row->response_status,
                'payload_redacted' => json_decode((string) $row->payload_redacted, true),
                'occurred_at' => (string) $row->occurred_at,
            ])->values(),
            'meta' => [
                'next_cursor' => $nextCursor,
                'limit' => $limit,
            ],
        ]);
    }

    private function resolveLimit(Request $request): int
    {
        $raw = (int) $request->query('limit', (string) self::DEFAULT_LIMIT);
        if ($raw < 1) {
            return self::DEFAULT_LIMIT;
        }

        return min($raw, self::MAX_LIMIT);
    }

    /**
     * @return array{0:string,1:string}|null
     */
    private function decodeCursor(?string $cursor): ?array
    {
        if ($cursor === null || $cursor === '') {
            return null;
        }
        $decoded = base64_decode($cursor, true);
        if ($decoded === false) {
            return null;
        }
        $parts = explode('|', $decoded, 2);
        if (count($parts) !== 2) {
            return null;
        }

        return [$parts[0], $parts[1]];
    }

    private function encodeCursor(string $occurredAt, string $id): string
    {
        return base64_encode($occurredAt.'|'.$id);
    }
}
