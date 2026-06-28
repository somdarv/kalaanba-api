<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Requests\Auth\ConfirmEmailRequest;
use App\Http\Resources\Auth\SessionResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Carbon;
use Kalaanba\Modules\Identity\Application\EmailVerification\ConfirmEmailCommand;
use Kalaanba\Modules\Identity\Application\EmailVerification\ConfirmEmailHandler;
use Kalaanba\Modules\Identity\Application\Registration\DuplicateChannelException;
use Kalaanba\Modules\Identity\Domain\EmailVerification\EmailVerificationPurpose;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * POST /api/v1/auth/email/verify
 *
 * Two response shapes (contract `oneOf`):
 *  - Registration purpose → 200 SessionResponse (Sanctum token minted).
 *  - BindEmail purpose    → 200 MeResponse (refreshed me view).
 */
final class EmailVerificationController extends Controller
{
    public function __construct(
        private readonly ConfirmEmailHandler $handler,
    ) {}

    public function store(ConfirmEmailRequest $request): JsonResponse|SessionResource
    {
        $validated = $request->validated();

        try {
            $result = $this->handler->handle(new ConfirmEmailCommand(
                plaintextToken: (string) $validated['token'],
                deviceName: $validated['device_name'] ?? null,
            ));
        } catch (DuplicateChannelException $e) {
            $code = $e->channel === 'email'
                ? 'identity.channel.email_already_bound'
                : 'identity.channel.phone_already_bound';
            throw new HttpException(409, $code);
        }

        if ($result->purpose === EmailVerificationPurpose::Registration) {
            return $this->mintSession($result->userId, (string) ($validated['device_name'] ?? 'api'));
        }

        return $this->respondWithMe($request, $result->userId);
    }

    private function mintSession(string $userId, string $deviceName): SessionResource
    {
        /** @var User $user */
        $user = User::query()->findOrFail($userId);

        $expiresAt = Carbon::now('UTC')->addDays(30);
        $token = $user->createToken($deviceName, ['*'], $expiresAt);
        $user->forceFill(['last_seen_at' => Carbon::now('UTC')])->save();

        return new SessionResource(
            $user,
            $token->plainTextToken,
            $expiresAt->toIso8601String(),
        );
    }

    private function respondWithMe(Request $request, string $userId): JsonResponse
    {
        $authedId = (string) ($request->user()?->getAuthIdentifier() ?? '');

        if ($authedId === '' || $authedId !== $userId) {
            // BindEmail confirm requires the original session that started
            // the bind — different user trying to consume the token = 403.
            throw new HttpException(403, 'identity.channel.bind_actor_mismatch');
        }

        /** @var User $user */
        $user = User::query()->findOrFail($userId);

        return new JsonResponse([
            'data' => [
                'id' => (string) $user->getKey(),
                'name' => $user->name,
                'email' => $user->email,
                'email_verified' => $user->email_verified_at !== null,
                'phone_bound' => $user->phone_e164_hash !== null,
            ],
        ]);
    }
}
