<?php

declare(strict_types=1);

namespace App\Http\Controllers\Player;

use App\Http\Requests\Player\UploadPlayerMediaRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\UploadedFile;
use Illuminate\Routing\Controller;
use Kalaanba\Modules\PlayerAffiliation\Application\PlayerNotFound;
use Kalaanba\Modules\PlayerAffiliation\Application\PlayerNotYours;
use Kalaanba\Modules\PlayerAffiliation\Application\UploadPlayerMedia;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

/**
 * POST /api/v1/players/{playerId}/media — multipart upload, returns the URL.
 *
 * Contract: contracts/api/player/post-players-id-media.v1.yaml.
 *
 * Thin, per engineering-standards §3: validate, call one application service,
 * return a resource. Ownership, storage, the headshot write and the moderation
 * event all belong to {@see UploadPlayerMedia}.
 *
 * **`moderation_status` is reported as `pending`, and that is not a placeholder
 * for "we did not check".** The upload raises `player.media_uploaded` through
 * the outbox and Moderation & Safety consumes it asynchronously (Constitution
 * Law 6), so at the instant this response is written no verdict exists yet.
 * Saying `cleared` here would be this engine asserting another engine's truth,
 * which Law 8 forbids in the other direction and good sense forbids in this
 * one. The owner sees their own photo immediately either way; a PUBLIC surface
 * must read the verdict before serving it to a stranger (Law 10).
 */
final class PlayerMediaController extends Controller
{
    public function __construct(
        private readonly UploadPlayerMedia $uploadMedia,
    ) {}

    public function store(UploadPlayerMediaRequest $request, string $playerId): JsonResponse
    {
        $user = $request->user();
        if (! $user instanceof User) {
            return $this->error(401, 'auth.unauthenticated', 'Authentication required.', $request);
        }

        /** @var UploadedFile $file */
        $file = $request->file('file');

        try {
            $stored = $this->uploadMedia->execute(
                playerId: $playerId,
                actorUserId: (string) $user->getAuthIdentifier(),
                file: $file,
                kind: $request->kind(),
            );
        } catch (PlayerNotFound) {
            return $this->error(404, 'player.profile_not_found', 'No player profile with that id.', $request);
        } catch (PlayerNotYours) {
            return $this->error(403, 'player.not_yours', 'That profile belongs to another player.', $request);
        }

        return new JsonResponse([
            'data' => [
                'url' => $stored->url,
                'kind' => $stored->kind->value,
                'width' => $stored->width,
                'height' => $stored->height,
                'moderation_status' => 'pending',
            ],
            'meta' => [
                'request_id' => (string) ($request->headers->get('X-Request-Id') ?? ''),
                'api_version' => 'v1',
            ],
        ], SymfonyResponse::HTTP_CREATED);
    }

    private function error(int $status, string $code, string $message, UploadPlayerMediaRequest $request): JsonResponse
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
