<?php

declare(strict_types=1);

namespace App\Http\Controllers\Club;

use App\Http\Requests\Club\UploadClubCrestRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Routing\Controller;
use Kalaanba\Modules\Club\Application\ClubNotFound;
use Kalaanba\Modules\Club\Application\ClubNotYours;
use Kalaanba\Modules\Club\Application\UploadClubCrest;

/**
 * POST /api/v1/clubs/{clubId}/crest — multipart upload, returns the URL.
 *
 * Thin per engineering-standards §3: validate, call one application service,
 * return a resource. Authority, storage, the row write and the moderation event
 * all belong to {@see UploadClubCrest}.
 *
 * **`moderation_status` is reported as `pending`, and that is not a placeholder
 * for "we did not check".** The upload raises `club.crest_updated` through the
 * outbox and Moderation & Safety consumes it asynchronously (Law 6), so at the
 * instant this response is written no verdict exists. Saying `cleared` would be
 * this engine asserting another engine's truth. The club's own admins see the
 * crest immediately either way; a PUBLIC surface must read the verdict before
 * serving it to a stranger (Law 10).
 *
 * Contract: contracts/api/club/post-clubs-id-crest.v1.yaml.
 */
final class ClubCrestController extends Controller
{
    public function __construct(
        private readonly UploadClubCrest $uploadCrest,
    ) {}

    public function store(UploadClubCrestRequest $request, string $clubId): JsonResponse
    {
        $user = $request->user();
        if (! $user instanceof User) {
            return $this->error(401, 'auth.unauthenticated', 'Authentication required.', $request);
        }

        /** @var UploadedFile $file */
        $file = $request->file('file');

        try {
            $stored = $this->uploadCrest->execute(
                clubId: $clubId,
                actorUserId: (string) $user->getAuthIdentifier(),
                file: $file,
            );
        } catch (ClubNotFound) {
            return $this->error(404, 'club.not_found', 'That club does not exist.', $request);
        } catch (ClubNotYours) {
            return $this->error(403, 'club.not_club_admin', 'Only a club owner or admin can change the crest.', $request);
        }

        return new JsonResponse([
            'data' => [
                'crest_url' => $stored->url,
                'width' => $stored->width,
                'height' => $stored->height,
                'moderation_status' => 'pending',
            ],
            'meta' => [],
        ], 201);
    }

    private function error(int $status, string $code, string $message, UploadClubCrestRequest|Request $request): JsonResponse
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
