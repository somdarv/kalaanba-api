<?php

declare(strict_types=1);

namespace App\Http\Controllers\Identity;

use App\Http\Requests\Identity\ConfirmPhoneChannelBindRequest;
use App\Http\Requests\Identity\StartEmailChannelBindRequest;
use App\Http\Requests\Identity\StartPhoneChannelBindRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Kalaanba\Modules\Identity\Application\ChannelBinding\ConfirmPhoneChannelBindHandler;
use Kalaanba\Modules\Identity\Application\ChannelBinding\StartEmailChannelBindHandler;
use Kalaanba\Modules\Identity\Application\ChannelBinding\StartPhoneChannelBindHandler;
use Kalaanba\Modules\Identity\Application\Registration\DuplicateChannelException;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Channel-binding endpoints — authenticated users adding a second channel.
 *
 *   POST /api/v1/users/me/channels/phone          (start)
 *   POST /api/v1/users/me/channels/phone/confirm  (confirm)
 *   POST /api/v1/users/me/channels/email          (start; confirm via
 *                                                  /api/v1/auth/email/verify)
 */
final class ChannelBindingController extends Controller
{
    public function __construct(
        private readonly StartPhoneChannelBindHandler $startPhone,
        private readonly ConfirmPhoneChannelBindHandler $confirmPhone,
        private readonly StartEmailChannelBindHandler $startEmail,
        private readonly bool $exposeEmailToken,
    ) {}

    public function startPhone(StartPhoneChannelBindRequest $request): JsonResponse
    {
        try {
            $issuance = $this->startPhone->handle($request->validated('phone_e164'));
        } catch (DuplicateChannelException) {
            throw new HttpException(409, 'identity.channel.phone_already_bound');
        }

        return new JsonResponse([
            'data' => [
                'expires_at' => $issuance->expiresAt->format(DATE_ATOM),
                'masked_phone' => $issuance->maskedPhone,
                'otp_length' => $issuance->codeLength,
            ],
        ], 202);
    }

    public function confirmPhone(ConfirmPhoneChannelBindRequest $request): JsonResponse
    {
        try {
            $this->confirmPhone->handle(
                userId: (string) $request->user()?->getAuthIdentifier(),
                phoneE164: (string) $request->validated('phone_e164'),
                otp: (string) $request->validated('otp'),
            );
        } catch (DuplicateChannelException) {
            throw new HttpException(409, 'identity.channel.phone_already_bound');
        }

        return new JsonResponse(status: 204);
    }

    public function startEmail(StartEmailChannelBindRequest $request): JsonResponse
    {
        try {
            $token = $this->startEmail->handle(
                userId: (string) $request->user()?->getAuthIdentifier(),
                email: (string) $request->validated('email'),
            );
        } catch (DuplicateChannelException) {
            throw new HttpException(409, 'identity.channel.email_already_bound');
        }

        $data = [
            'status' => 'email_verification_pending',
            'expires_at' => $token->expiresAt->format(DATE_ATOM),
        ];

        if ($this->exposeEmailToken && $token->plaintext !== null) {
            $data['verification_token'] = $token->plaintext;
        }

        return new JsonResponse(['data' => $data], 202);
    }
}
