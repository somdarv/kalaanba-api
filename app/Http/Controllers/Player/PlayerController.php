<?php

declare(strict_types=1);

namespace App\Http\Controllers\Player;

use App\Http\Requests\Player\CreatePlayerRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Kalaanba\Modules\PlayerAffiliation\Application\CreatePlayerProfile;
use Kalaanba\Modules\PlayerAffiliation\Application\PlayerProfileVocabulary;
use Kalaanba\Modules\PlayerAffiliation\Domain\Player;
use Kalaanba\Modules\PlayerAffiliation\Domain\PlayerAvailability;

/**
 * Self-service player-profile creation (engine doc §4, §22): a user becomes a
 * CLAIMED FREE-AGENT player. Authenticated + idempotent (one player per user).
 *
 * - POST /api/v1/players
 *
 * Contract: contracts/api/player/post-players.v1.yaml.
 */
final class PlayerController extends Controller
{
    public function __construct(
        private readonly CreatePlayerProfile $create,
        private readonly PlayerProfileVocabulary $vocabulary,
    ) {}

    public function store(CreatePlayerRequest $request): JsonResponse
    {
        $user = $request->user();
        if (! $user instanceof User) {
            return $this->error(401, 'auth.unauthenticated', 'Authentication required.', $request);
        }

        $validated = $request->validated();
        $preferred = $validated['preferred_number'] ?? null;
        $position = $validated['primary_position'] ?? null;
        $headshot = $validated['headshot_url'] ?? null;

        $result = $this->create->execute(
            userId: (string) $user->getAuthIdentifier(),
            firstName: (string) $validated['first_name'],
            lastName: (string) $validated['last_name'],
            stageName: (string) $validated['stage_name'],
            preferredNumber: $preferred !== null ? (int) $preferred : null,
            primaryPosition: is_string($position) && $position !== '' ? $position : null,
            availability: $this->resolveAvailability($validated['availability_status'] ?? null),
            headshotUrl: is_string($headshot) && $headshot !== '' ? $headshot : null,
        );

        return new JsonResponse(
            ['data' => $this->present($result['player']), 'meta' => []],
            $result['created'] ? 201 : 200,
        );
    }

    /**
     * The caller's choice wins; otherwise the configured default (§12),
     * resolved by the same vocabulary the form was rendered from.
     */
    private function resolveAvailability(mixed $requested): PlayerAvailability
    {
        if (is_string($requested) && $requested !== '') {
            $parsed = PlayerAvailability::tryFrom($requested);
            if ($parsed !== null) {
                return $parsed;
            }
        }

        return $this->vocabulary->availabilityDefault();
    }

    /**
     * @return array<string, mixed>
     */
    private function present(Player $player): array
    {
        return [
            'id' => $player->id,
            'user_id' => $player->userId,
            'first_name' => $player->firstName,
            'last_name' => $player->lastName,
            'stage_name' => $player->stageName,
            'preferred_number' => $player->preferredNumber,
            'primary_position' => $player->primaryPosition,
            'availability_status' => $player->availability->value,
            'market_status' => $player->marketStatus->value,
            'claim_status' => $player->claimStatus->value,
            'headshot_url' => $player->headshotUrl,
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
