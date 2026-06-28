<?php

declare(strict_types=1);

namespace App\Http\Controllers\Identity;

use App\Http\Requests\Identity\UploadAvatarRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\UploadedFile;
use Illuminate\Routing\Controller;
use Kalaanba\Modules\Identity\Application\UploadAvatarService;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

/**
 * POST /api/v1/users/me/avatar — multipart upload, returns the new URL.
 *
 * Two-step on purpose (engine doc §8): upload is idempotent and isolated
 * from the PATCH that records the URL on the user row.
 */
final class AvatarController extends Controller
{
    public function __construct(
        private readonly UploadAvatarService $uploadAvatar,
    ) {}

    public function store(UploadAvatarRequest $request): JsonResponse
    {
        $user = $request->user();
        \abort_if($user === null, SymfonyResponse::HTTP_UNAUTHORIZED);
        \assert($user instanceof User);

        /** @var UploadedFile $file */
        $file = $request->file('file');

        $url = $this->uploadAvatar->handle((string) $user->getKey(), $file);

        return new JsonResponse([
            'data' => ['avatar_url' => $url],
            'meta' => [],
        ], SymfonyResponse::HTTP_CREATED);
    }
}
