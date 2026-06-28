<?php

declare(strict_types=1);

namespace App\Http\Controllers\Identity;

use App\Http\Requests\Identity\UpdateProfileRequest;
use App\Http\Resources\Identity\MeResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Kalaanba\Modules\Identity\Application\UpdateProfileService;
use Kalaanba\Modules\Identity\Application\UserProfileRepository;
use Kalaanba\Modules\Identity\Domain\ProfileUpdate;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

/**
 * GET /api/v1/users/me — read own profile.
 * PATCH /api/v1/users/me — update name / area_id / avatar_url.
 *
 * Engine doc: docs/engines/identity/Identity_Engine_System_Document.md §8.
 */
final class MeController extends Controller
{
    public function __construct(
        private readonly UpdateProfileService $updateProfile,
        private readonly UserProfileRepository $repository,
    ) {}

    public function show(Request $request): JsonResponse
    {
        $user = $request->user();
        \abort_if($user === null, SymfonyResponse::HTTP_UNAUTHORIZED);
        \assert($user instanceof User);

        $snapshot = $this->repository->find((string) $user->getKey());
        \abort_if($snapshot === null, SymfonyResponse::HTTP_NOT_FOUND);

        return new JsonResponse([
            'data' => (new MeResource($snapshot))->resolve(),
            'meta' => [],
        ]);
    }

    public function update(UpdateProfileRequest $request): JsonResponse
    {
        $user = $request->user();
        \abort_if($user === null, SymfonyResponse::HTTP_UNAUTHORIZED);
        \assert($user instanceof User);

        /** @var array{name?: string, area_id?: string|null, avatar_url?: string|null} $payload */
        $payload = $request->validated();

        $snapshot = $this->updateProfile->handle(
            (string) $user->getKey(),
            new ProfileUpdate(
                name: $payload['name'] ?? null,
                areaId: \array_key_exists('area_id', $payload) ? $payload['area_id'] : null,
                avatarUrl: \array_key_exists('avatar_url', $payload) ? $payload['avatar_url'] : null,
            ),
        );
        \abort_if($snapshot === null, SymfonyResponse::HTTP_NOT_FOUND);

        return new JsonResponse([
            'data' => (new MeResource($snapshot))->resolve(),
            'meta' => [],
        ]);
    }
}
