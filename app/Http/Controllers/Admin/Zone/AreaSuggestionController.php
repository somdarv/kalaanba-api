<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\Zone;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Kalaanba\Modules\Zone\Application\ApproveAreaSuggestion;
use Kalaanba\Modules\Zone\Application\RejectAreaSuggestion;
use Kalaanba\Modules\Zone\Domain\AreaSuggestionRepository;
use Kalaanba\Modules\Zone\Domain\AreaSuggestionStatus;
use Kalaanba\Modules\Zone\Domain\GeographyReader;
use Ramsey\Uuid\Uuid;
use RuntimeException;
use Throwable;

/**
 * Admin surface for the Zone engine's Area-suggestion review queue.
 *
 * - GET    /api/v1/admin/zone/area-suggestions
 * - POST   /api/v1/admin/zone/area-suggestions/{id}/approve
 * - POST   /api/v1/admin/zone/area-suggestions/{id}/reject
 *
 * Gated by route middleware (`auth:sanctum` + `super_admin`). Approval / reject
 * idempotency is enforced by the Application services (terminal short-circuit
 * + deterministic UUIDv5 dedupe), so callers can safely retry.
 *
 * Constitution §1.2 (configurability), §1.5 (audited via AdminAuditMiddleware),
 * §1.6 (event-first via the OutboxWriter inside the Application services),
 * §1.14 (idempotent writes).
 */
final class AreaSuggestionController extends Controller
{
    private const DEFAULT_LIMIT = 50;

    private const MAX_LIMIT = 200;

    /**
     * Stable namespace for deriving a UUIDv5 from a User integer id.
     *
     * Workaround: the application-layer DTOs for Zone reviewer ids are typed
     * as UUID strings, but `users.id` is currently a BIGINT. Until the user
     * identity surface is unified to UUID (out-of-scope for this slice), we
     * derive a deterministic UUID from the auth id so the FK columns remain
     * UUID-shaped and joinable across the audit trail.
     */
    private const REVIEWER_UUID_NAMESPACE = '8c2f9d0a-2c5b-4e3e-9c1e-6a3b1a0e0010';

    public function __construct(
        private readonly ApproveAreaSuggestion $approve,
        private readonly RejectAreaSuggestion $reject,
        private readonly AreaSuggestionRepository $repository,
        private readonly GeographyReader $geography,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $limit = $this->resolveLimit($request);
        $status = $this->resolveStatus($request);

        $query = DB::table('area_suggestions')
            ->orderBy('submitted_at', 'desc')
            ->orderBy('id', 'desc')
            ->limit($limit);

        if ($status !== null) {
            $query->where('status', $status->value);
        }

        $rows = $query->get();

        return new JsonResponse([
            'data' => $rows->map(fn ($row): array => $this->present($row))->values(),
            'meta' => [
                'limit' => $limit,
                'count' => $rows->count(),
                'status_filter' => $status?->value,
            ],
        ]);
    }

    public function approve(Request $request, string $id): JsonResponse
    {
        $user = $request->user();
        if (! $user instanceof User) {
            return $this->error(401, 'auth.unauthenticated', 'Authentication required.', $request);
        }

        $existing = $this->repository->findById($id);
        if ($existing === null) {
            return $this->error(404, 'zone.suggestion_not_found', 'Suggestion not found.', $request);
        }

        $targetZoneId = $existing->proposedZoneId;
        $finalName = (string) ($request->input('final_name') ?? $existing->proposedName);
        $reviewNote = $request->input('review_note');
        $reviewNote = is_string($reviewNote) && $reviewNote !== '' ? $reviewNote : null;

        if (! is_string($targetZoneId) || $targetZoneId === '') {
            $targetZoneId = (string) $request->input('zone_id');
        }

        if ($targetZoneId === '') {
            return $this->error(422, 'zone.zone_id_required', 'A target zone_id is required when the suggestion has none.', $request);
        }

        if ($this->geography->findZoneById($targetZoneId) === null) {
            return $this->error(422, 'zone.zone_not_found', 'Target zone does not exist.', $request);
        }

        try {
            $updated = $this->approve->execute(
                suggestionId: $id,
                reviewerUserId: $this->reviewerUuid($user),
                targetZoneId: $targetZoneId,
                finalName: $finalName,
                reviewNote: $reviewNote,
            );
        } catch (RuntimeException $e) {
            return $this->error(404, 'zone.suggestion_not_found', $e->getMessage(), $request);
        } catch (Throwable $e) {
            return $this->error(500, 'zone.approve_failed', $e->getMessage(), $request);
        }

        return new JsonResponse([
            'data' => [
                'id' => $updated->id,
                'status' => $updated->status->value,
                'resulting_area_id' => $updated->resultingAreaId,
                'reviewed_by_user_id' => $updated->reviewedByUserId,
                'reviewed_at' => $updated->reviewedAt?->format(DATE_ATOM),
                'review_note' => $updated->reviewNote,
            ],
        ]);
    }

    public function rejectSuggestion(Request $request, string $id): JsonResponse
    {
        $user = $request->user();
        if (! $user instanceof User) {
            return $this->error(401, 'auth.unauthenticated', 'Authentication required.', $request);
        }

        if ($this->repository->findById($id) === null) {
            return $this->error(404, 'zone.suggestion_not_found', 'Suggestion not found.', $request);
        }

        $reviewNote = $request->input('review_note');
        $reviewNote = is_string($reviewNote) && $reviewNote !== '' ? $reviewNote : null;

        try {
            $updated = $this->reject->execute(
                suggestionId: $id,
                reviewerUserId: $this->reviewerUuid($user),
                reviewNote: $reviewNote,
            );
        } catch (Throwable $e) {
            return $this->error(500, 'zone.reject_failed', $e->getMessage(), $request);
        }

        return new JsonResponse([
            'data' => [
                'id' => $updated->id,
                'status' => $updated->status->value,
                'reviewed_by_user_id' => $updated->reviewedByUserId,
                'reviewed_at' => $updated->reviewedAt?->format(DATE_ATOM),
                'review_note' => $updated->reviewNote,
            ],
        ]);
    }

    /**
     * @return array<string,mixed>
     */
    private function present(object $row): array
    {
        return [
            'id' => (string) $row->id,
            'city_hub_id' => (string) $row->city_hub_id,
            'proposed_zone_id' => $row->proposed_zone_id !== null ? (string) $row->proposed_zone_id : null,
            'proposed_name' => (string) $row->proposed_name,
            'note' => $row->note !== null ? (string) $row->note : null,
            'submitted_by_user_id' => (string) $row->submitted_by_user_id,
            'status' => (string) $row->status,
            'reviewed_by_user_id' => $row->reviewed_by_user_id !== null ? (string) $row->reviewed_by_user_id : null,
            'review_note' => $row->review_note !== null ? (string) $row->review_note : null,
            'resulting_area_id' => $row->resulting_area_id !== null ? (string) $row->resulting_area_id : null,
            'submitted_at' => (string) $row->submitted_at,
            'reviewed_at' => $row->reviewed_at !== null ? (string) $row->reviewed_at : null,
        ];
    }

    private function resolveLimit(Request $request): int
    {
        $raw = (int) $request->query('limit', (string) self::DEFAULT_LIMIT);
        if ($raw < 1) {
            return self::DEFAULT_LIMIT;
        }

        return min($raw, self::MAX_LIMIT);
    }

    private function resolveStatus(Request $request): ?AreaSuggestionStatus
    {
        $value = $request->query('status');
        if (! is_string($value) || $value === '') {
            return null;
        }

        return AreaSuggestionStatus::tryFrom($value);
    }

    private function reviewerUuid(User $user): string
    {
        return (string) Uuid::uuid5(
            self::REVIEWER_UUID_NAMESPACE,
            'user:'.((string) $user->getAuthIdentifier()),
        );
    }

    private function error(int $status, string $code, string $message, Request $request): JsonResponse
    {
        return new JsonResponse([
            'error' => [
                'code' => $code,
                'message' => $message,
                'details' => [],
                'request_id' => (string) ($request->headers->get('X-Request-Id') ?? ''),
            ],
        ], $status);
    }
}
