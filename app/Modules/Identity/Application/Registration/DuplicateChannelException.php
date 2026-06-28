<?php

declare(strict_types=1);

namespace Kalaanba\Modules\Identity\Application\Registration;

use RuntimeException;

/**
 * Thrown by {@see UserRegistrationRepository} adapters when a unique
 * constraint trips on email or phone-hash. Application layer maps to a
 * 409 with the stable error code `auth.phone_in_use` or `auth.email_in_use`.
 */
final class DuplicateChannelException extends RuntimeException
{
    public function __construct(public readonly string $channel)
    {
        parent::__construct("Channel already in use: {$channel}");
    }
}
