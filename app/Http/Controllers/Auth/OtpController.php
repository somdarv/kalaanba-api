<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Requests\Auth\RequestOtpRequest;
use App\Http\Requests\Auth\VerifyOtpRequest;
use App\Http\Resources\Auth\SessionResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use Kalaanba\Support\Auth\Otp\Exceptions\OtpException;
use Kalaanba\Support\Auth\Otp\OtpService;
use Kalaanba\Support\Auth\PhoneHash;

final class OtpController extends Controller
{
    public function __construct(
        private readonly OtpService $otpService,
        private readonly PhoneHash $phoneHash,
    ) {}

    /**
     * POST /api/v1/auth/otp/request — issue an OTP via the active provider.
     */
    public function request(RequestOtpRequest $request): JsonResponse
    {
        $issuance = $this->otpService->issue($request->validated()['phone_e164']);

        return new JsonResponse([
            'data' => [
                'expires_at' => $issuance->expiresAt->format(DATE_ATOM),
                'masked_phone' => $issuance->maskedPhone,
                'otp_length' => $issuance->codeLength,
            ],
        ], 202);
    }

    /**
     * POST /api/v1/auth/otp/verify — consume an OTP, issue a Sanctum token.
     */
    public function verify(VerifyOtpRequest $request): SessionResource
    {
        $validated = $request->validated();
        $phoneE164 = $validated['phone_e164'];

        $user = $this->findActiveUserByPhone($phoneE164);
        $this->consumeOtp($phoneE164, $validated['otp']);

        $deviceName = $validated['device_name'] ?? 'api';
        $expiresAt = Carbon::now('UTC')->addDays(30);
        $token = $user->createToken($deviceName, ['*'], $expiresAt);
        $user->forceFill(['last_seen_at' => Carbon::now('UTC')])->save();

        return new SessionResource(
            $user,
            $token->plainTextToken,
            $expiresAt->toIso8601String(),
        );
    }

    private function findActiveUserByPhone(string $phoneE164): User
    {
        $user = User::query()
            ->where('phone_e164_hash', $this->phoneHash->hash($phoneE164))
            ->whereNull('archived_at')
            ->first();

        if ($user === null) {
            throw ValidationException::withMessages([
                'phone_e164' => ['auth.otp_not_found'],
            ]);
        }

        return $user;
    }

    private function consumeOtp(string $phoneE164, string $submittedCode): void
    {
        try {
            $this->otpService->verify($phoneE164, $submittedCode);
        } catch (OtpException $e) {
            throw ValidationException::withMessages([
                'otp' => [$e->errorCode()],
            ]);
        }
    }
}
