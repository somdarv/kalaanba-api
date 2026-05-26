<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;

/**
 * Read-only listing of the `admin_config` registry surface. Super Admin only —
 * configs are sensitive (some critical-tier). Editing flows through a
 * proposal/approval workflow that is NOT exposed here (Constitution §1.2,
 * Admin & Governance engine doc §8).
 */
final class ConfigController extends Controller
{
    private const DEFAULT_LIMIT = 50;

    private const MAX_LIMIT = 200;

    public function index(Request $request): JsonResponse
    {
        $limit = $this->resolveLimit($request);
        $query = DB::table('admin_config')
            ->orderBy('key', 'asc')
            ->limit($limit);

        $engine = $request->query('engine');
        if (is_string($engine) && $engine !== '') {
            // Engine prefix lives in the key itself (e.g. "zone.foo").
            $query->where('key', 'like', $engine.'.%');
        }

        $approvalLevel = $request->query('approval_level');
        if (is_string($approvalLevel) && $approvalLevel !== '') {
            $query->where('approval_level', $approvalLevel);
        }

        $rows = $query->get();

        return new JsonResponse([
            'data' => $rows->map(fn ($row): array => [
                'key' => (string) $row->key,
                'scope' => (string) $row->scope,
                'scope_id' => $row->scope_id !== null ? (string) $row->scope_id : null,
                'value' => (string) $row->value,
                'version' => (int) $row->version,
                'approval_level' => (string) $row->approval_level,
                'effective_from' => (string) $row->effective_from,
                'change_reason' => $row->change_reason !== null ? (string) $row->change_reason : null,
                'updated_at' => (string) $row->updated_at,
            ])->values(),
            'meta' => [
                'limit' => $limit,
                'count' => $rows->count(),
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
}
