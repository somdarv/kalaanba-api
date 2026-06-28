<?php

declare(strict_types=1);

namespace App\Http\Controllers\Identity;

use App\Http\Resources\Identity\PublicUserResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Kalaanba\Modules\Identity\Application\GetPublicProfileQuery;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

/**
 * GET /api/v1/users/{id} — public projection.
 *
 * Auth is optional; the rate limit is tighter when anonymous (handled at
 * the route layer via `throttle:identity-public-profile`).
 *
 * Returns 404 when the user is archived or does not exist — existence is
 * not leaked (engineering-standards §11).
 *
 * Engine doc: docs/engines/identity/Identity_Engine_System_Document.md §12.
 */
final class UserShowController extends Controller
{
    public function __construct(
        private readonly GetPublicProfileQuery $query,
    ) {}

    public function show(string $id): JsonResponse
    {
        $profile = $this->query->handle($id);
        \abort_if($profile === null, SymfonyResponse::HTTP_NOT_FOUND);

        return new JsonResponse([
            'data' => (new PublicUserResource($profile))->resolve(),
            'meta' => [],
        ]);
    }
}
