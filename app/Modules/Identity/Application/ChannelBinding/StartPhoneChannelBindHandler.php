<?php

declare(strict_types=1);

namespace Kalaanba\Modules\Identity\Application\ChannelBinding;

use Kalaanba\Modules\Identity\Application\Registration\DuplicateChannelException;
use Kalaanba\Modules\Identity\Application\Registration\UserRegistrationRepository;
use Kalaanba\Support\Auth\Otp\OtpIssuance;
use Kalaanba\Support\Auth\Otp\OtpService;
use Kalaanba\Support\Auth\PhoneHash;

/**
 * Start binding a phone channel to an already-authenticated user.
 *
 * Issues an OTP through the existing {@see OtpService}. The user must
 * complete via {@see ConfirmPhoneChannelHandler}.
 *
 * Pre-empts the OTP send with a duplicate-channel check so we never
 * notify the wrong handset that a code was sent.
 */
final readonly class StartPhoneChannelBindHandler
{
    public function __construct(
        private UserRegistrationRepository $users,
        private OtpService $otpService,
        private PhoneHash $phoneHash,
    ) {}

    public function handle(string $phoneE164): OtpIssuance
    {
        $hash = $this->phoneHash->hash($phoneE164);

        if ($this->users->phoneInUse($hash)) {
            throw new DuplicateChannelException('phone');
        }

        // OtpService keys by phone hash; the matching confirm handler
        // re-checks the authenticated user is the one binding.
        return $this->otpService->issue($phoneE164);
    }
}
