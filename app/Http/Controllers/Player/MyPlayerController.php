<?php

declare(strict_types=1);

namespace App\Http\Controllers\Player;

use App\Http\Requests\Player\UpdatePlayerRequest;
use App\Http\Resources\Player\MyPlayerResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Kalaanba\Modules\PlayerAffiliation\Application\CardConfidenceLadder;
use Kalaanba\Modules\PlayerAffiliation\Application\PlayerNotFound;
use Kalaanba\Modules\PlayerAffiliation\Application\PlayerNotYours;
use Kalaanba\Modules\PlayerAffiliation\Application\UpdatePlayerProfile;
use Kalaanba\Modules\PlayerAffiliation\Domain\Player;
use Kalaanba\Modules\PlayerAffiliation\Domain\PlayerRepository;
use Kalaanba\Modules\PlayerAffiliation\Domain\VerifiedStatsReader;

/**
 * The owner's own player record — the surface behind `/me`.
 *
 *  - GET   /api/v1/players/me
 *  - PATCH /api/v1/players/{playerId}
 *
 * Contracts: contracts/api/player/get-players-me.v1.yaml,
 *            contracts/api/player/patch-players-id.v1.yaml.
 *
 * Separate from {@see PlayerController} (which owns creation) so neither grows
 * past the thin-controller rule: validate, call one application service,
 * return a resource.
 */
final class MyPlayerController extends Controller
{
    public function __construct(
        private readonly PlayerRepository $players,
        private readonly VerifiedStatsReader $stats,
        private readonly CardConfidenceLadder $ladder,
        private readonly UpdatePlayerProfile $update,
    ) {}

    public function show(Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $user instanceof User) {
            return $this->error(401, 'auth.unauthenticated', 'Authentication required.', $request);
        }

        $player = $this->players->findByUserId((string) $user->getAuthIdentifier());
        if ($player === null) {
            // Expected and common, not a failure: post-signup users are
            // `role=user` and player-hood is opt-in (§22). `/me` renders its
            // no-card half on this.
            return $this->error(404, 'player.profile_not_found', 'No player profile for this account.', $request);
        }

        return new JsonResponse(['data' => $this->present($player, $request), 'meta' => []]);
    }

    public function update(UpdatePlayerRequest $request, string $playerId): JsonResponse
    {
        $user = $request->user();
        if (! $user instanceof User) {
            return $this->error(401, 'auth.unauthenticated', 'Authentication required.', $request);
        }

        try {
            $player = $this->update->execute(
                playerId: $playerId,
                actorUserId: (string) $user->getAuthIdentifier(),
                changes: $request->changes(),
            );
        } catch (PlayerNotFound) {
            return $this->error(404, 'player.profile_not_found', 'No player profile with that id.', $request);
        } catch (PlayerNotYours) {
            return $this->error(403, 'player.not_yours', 'That profile belongs to another player.', $request);
        }

        return new JsonResponse(['data' => $this->present($player, $request), 'meta' => []]);
    }

    /**
     * Confidence and the verified record are read fresh alongside the player.
     * Both are derived from another engine's truth (§13, §14), so they are
     * never cached on the player row — a stale tier is a wrong claim about how
     * much football is behind a card.
     *
     * @return array<string, mixed>
     */
    private function present(Player $player, Request $request): array
    {
        $resource = new MyPlayerResource(
            $player,
            $this->ladder->resolve($this->stats->confirmedMatchCountFor($player->id)),
            $this->stats->recordFor($player->id),
        );

        return $resource->toArray($request);
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
