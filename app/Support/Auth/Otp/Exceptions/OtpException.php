<?php

declare(strict_types=1);

namespace Kalaanba\Support\Auth\Otp\Exceptions;

use RuntimeException;

abstract class OtpException extends RuntimeException
{
    /**
     * Stable engine.action error code surfaced to the API boundary.
     */
    abstract public function errorCode(): string;
}
