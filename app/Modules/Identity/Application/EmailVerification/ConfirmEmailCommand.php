<?php

declare(strict_types=1);

namespace Kalaanba\Modules\Identity\Application\EmailVerification;

/**
 * Input to {@see ConfirmEmailHandler}.
 *
 * `deviceName` is only meaningful on the registration-purpose path,
 * where the Http boundary mints a Sanctum token from the result. On the
 * bind_email path the user is already authenticated and `deviceName`
 * is ignored.
 */
final readonly class ConfirmEmailCommand
{
    public function __construct(
        public string $plaintextToken,
        public ?string $deviceName = null,
    ) {}
}
