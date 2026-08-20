<?php

declare(strict_types=1);

namespace App\Http\Controllers\Club;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Kalaanba\Modules\Club\Domain\ClubReader;
use Kalaanba\Modules\PlayerAffiliation\Application\AffiliationDenied;
use Kalaanba\Modules\PlayerAffiliation\Application\DecideJoinRequest;
use Kalaanba\Modules\PlayerAffiliation\Application\RequestToJoinClub;
use Kalaanba\Modules\PlayerAffiliation\Domain\Affiliation;
use Kalaanba\Modules\PlayerAffiliation\Domain\AffiliationRepository;
use Kalaanba\Modules\PlayerAffiliation\Domain\PlayerRepository;
use RuntimeException;

/**
 * Club join requests — the affiliation request → accept lifecycle
 * (Player & Affiliation engine doc §8, §11). Routes are nested under the club
 * for clarity; the affiliation truth belongs to the Player & Affiliation engine.
 *
 * - POST /api/v1/clubs/{club}/join-requests               player requests
 * - GET  /api/v1/clubs/{club}/join-requests               admin lists pending
 * - POST /api/v1/clubs/{club}/join-requests/{id}/accept   admin accepts
 * - POST /api/v1/clubs/{club}/join-requests/{id}/decline  admin declines
 */
final class AffiliationController extends Controller
{
    public function __construct(
        private readonly RequestToJoinClub $request,
        private readonly DecideJoinRequest $decide,
        private readonly AffiliationRepository $affiliations,
        private readonly PlayerRepository $players,
        private readonly ClubReader $clubs,
    ) {}

    public function store(Request $httpRequest, string $clubId): JsonResponse
    {
        $user = $httpRequest->user();
        if (! $user instanceof User) {
            return $this->error(401, 'auth.unauthenticated', 'Authentication required.', $httpRequest);
        }

        try {
            $result = $this->request->execute((string) $user->getAuthIdentifier(), $clubId);
        } catch (RuntimeException $e) {
            return $this->error(422, 'affiliation.request_invalid', $e->getMessage(), $httpRequest);
        }

        return new JsonResponse(
            ['data' => $this->present($result['affiliation']), 'meta' => []],
            $result['created'] ? 201 : 200,
        );
    }

    public function index(Request $httpRequest, string $clubId): JsonResponse
    {
        $user = $httpRequest->user();
        if (! $user instanceof User) {
            return $this->error(401, 'auth.unauthenticated', 'Authentication required.', $httpRequest);
        }
        if (! $this->clubs->userIsClubAdmin($clubId, (string) $user->getAuthIdentifier())) {
            return $this->error(403, 'affiliation.not_club_admin', 'Only a club owner or admin can view join requests.', $httpRequest);
        }

        $pending = array_map(
            fn (Affiliation $a): array => $this->presentWithPlayer($a),
            $this->affiliations->listPendingForClub($clubId),
        );

        return new JsonResponse(['data' => $pending, 'meta' => ['count' => count($pending)]], 200);
    }

    public function accept(Request $httpRequest, string $clubId, string $affiliationId): JsonResponse
    {
        return $this->applyDecision($httpRequest, $affiliationId, true);
    }

    public function decline(Request $httpRequest, string $clubId, string $affiliationId): JsonResponse
    {
        return $this->applyDecision($httpRequest, $affiliationId, false);
    }

    private function applyDecision(Request $httpRequest, string $affiliationId, bool $accept): JsonResponse
    {
        $user = $httpRequest->user();
        if (! $user instanceof User) {
            return $this->error(401, 'auth.unauthenticated', 'Authentication required.', $httpRequest);
        }

        try {
            $affiliation = $this->decide->execute($affiliationId, (string) $user->getAuthIdentifier(), $accept);
        } catch (AffiliationDenied $e) {
            return $this->error(403, 'affiliation.not_club_admin', $e->getMessage(), $httpRequest);
        } catch (RuntimeException $e) {
            return $this->error(422, 'affiliation.decision_invalid', $e->getMessage(), $httpRequest);
        }

        return new JsonResponse(['data' => $this->present($affiliation), 'meta' => []], 200);
    }

    /**
     * @return array<string, mixed>
     */
    private function present(Affiliation $a): array
    {
        return [
            'id' => $a->id,
            'player_id' => $a->playerId,
            'club_id' => $a->clubId,
            'state' => $a->state->value,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function presentWithPlayer(Affiliation $a): array
    {
        $player = $this->players->findById($a->playerId);

        return [
            'id' => $a->id,
            'player_id' => $a->playerId,
            'club_id' => $a->clubId,
            'state' => $a->state->value,
            'player' => $player === null ? null : [
                'stage_name' => $player->stageName,
                'primary_position' => $player->primaryPosition,
            ],
        ];
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
