<?php

declare(strict_types=1);

namespace Kalaanba\Support\Auth\Otp\Exceptions;

final class OtpAttemptsExhaustedException extends OtpException
{
    public function errorCode(): string
    {
        return 'auth.otp_attempts_exhausted';
    }
}
