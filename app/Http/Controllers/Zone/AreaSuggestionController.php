<?php

declare(strict_types=1);

namespace App\Http\Controllers\Zone;

use App\Http\Requests\Zone\SuggestAreaRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Kalaanba\Modules\Zone\Application\SubmitAreaSuggestion;
use RuntimeException;

/**
 * Public area-suggestion submission (engine doc §5: "if the area is missing,
 * the user suggests the area and admin maps it after review"). Authenticated;
 * the submitter is recorded. Idempotent (suggestion UUID is the natural key).
 *
 * - POST /api/v1/zone/area-suggestions
 *
 * Contract: contracts/api/zone/post-area-suggestions.v1.yaml. The admin review
 * side lives in App\Http\Controllers\Admin\Zone\AreaSuggestionController.
 */
final class AreaSuggestionController extends Controller
{
    public function __construct(
        private readonly SubmitAreaSuggestion $submit,
    ) {}

    public function store(SuggestAreaRequest $request): JsonResponse
    {
        $user = $request->user();
        if (! $user instanceof User) {
            return $this->error(401, 'auth.unauthenticated', 'Authentication required.', $request);
        }

        $validated = $request->validated();
        $note = $validated['note'] ?? null;

        try {
            $suggestion = $this->submit->execute(
                cityHubId: (string) $validated['city_hub_id'],
                proposedZoneId: null,
                proposedName: (string) $validated['proposed_name'],
                note: is_string($note) && $note !== '' ? $note : null,
                submittedByUserId: (string) $user->getAuthIdentifier(),
            );
        } catch (RuntimeException $e) {
            return $this->error(422, 'zone.city_hub_not_found', $e->getMessage(), $request);
        }

        return new JsonResponse([
            'data' => [
                'id' => $suggestion->id,
                'status' => $suggestion->status->value,
            ],
            'meta' => [],
        ], 201);
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
