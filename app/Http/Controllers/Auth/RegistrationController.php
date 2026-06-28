<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Requests\Auth\RegisterUserRequest;
use App\Http\Resources\Auth\SessionResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Illuminate\Support\Carbon;
use Kalaanba\Modules\Identity\Application\Registration\DuplicateChannelException;
use Kalaanba\Modules\Identity\Application\Registration\RegisterUserCommand;
use Kalaanba\Modules\Identity\Application\Registration\RegisterUserHandler;
use Kalaanba\Modules\Identity\Application\Registration\RegisterUserResult;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * POST /api/v1/auth/registration
 *
 * Two response shapes (contract `oneOf`):
 *  - Phone path → 201 SessionResponse (Sanctum token minted here).
 *  - Email path → 202 EmailVerificationStartedResponse (user_id +
 *                  expires_at, plaintext token included only when the
 *                  `auth.expose_email_verify_token` config flag is on —
 *                  intended for the log/dev notification driver).
 */
final class RegistrationController extends Controller
{
    public function __construct(
        private readonly RegisterUserHandler $handler,
    ) {}

    public function store(RegisterUserRequest $request): JsonResponse|SessionResource
    {
        $validated = $request->validated();

        try {
            $result = $this->handler->handle(new RegisterUserCommand(
                channel: (string) $validated['channel'],
                name: (string) $validated['name'],
                areaId: isset($validated['area_id']) ? (string) $validated['area_id'] : null,
                registeredVia: (string) ($validated['registered_via'] ?? 'self'),
                phoneE164: $validated['phone_e164'] ?? null,
                otp: $validated['otp'] ?? null,
                email: $validated['email'] ?? null,
                password: $validated['password'] ?? null,
                deviceName: $validated['device_name'] ?? null,
            ));
        } catch (DuplicateChannelException $e) {
            $code = $e->channel === 'phone' ? 'auth.phone_in_use' : 'auth.email_in_use';
            throw new HttpException(409, $code);
        }

        return $result->isPhone()
            ? $this->respondWithSession($result, (string) ($validated['device_name'] ?? 'api'))
            : $this->respondWithEmailVerification($result);
    }

    private function respondWithSession(RegisterUserResult $result, string $deviceName): SessionResource
    {
        /** @var User $user */
        $user = User::query()->findOrFail($result->userId);

        $expiresAt = Carbon::now('UTC')->addDays(30);
        $token = $user->createToken($deviceName, ['*'], $expiresAt);
        $user->forceFill(['last_seen_at' => Carbon::now('UTC')])->save();

        return (new SessionResource(
            $user,
            $token->plainTextToken,
            $expiresAt->toIso8601String(),
        ))->additional(['meta' => ['status' => 'session_issued']]);
    }

    private function respondWithEmailVerification(RegisterUserResult $result): JsonResponse
    {
        $payload = [
            'data' => [
                'status' => 'email_verification_pending',
                'user_id' => $result->userId,
                'expires_at' => $result->verificationExpiresAt?->format(DATE_ATOM),
            ],
        ];

        if ($result->verificationToken !== null) {
            $payload['data']['verification_token'] = $result->verificationToken;
        }

        return new JsonResponse($payload, 202);
    }
}
