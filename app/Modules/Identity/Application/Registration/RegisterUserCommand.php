<?php

declare(strict_types=1);

namespace Kalaanba\Modules\Identity\Application\Registration;

/**
 * Input to {@see RegisterUserHandler}.
 *
 * Constructed by the Http boundary after FormRequest validation. Exactly
 * one of (`otp` + `phoneE164` + `deviceName`) OR (`email` + `password`)
 * is populated, determined by `channel`. The handler enforces this
 * invariant — Application is the last gate before Domain.
 *
 * `registeredVia` is `self` for public registration; `invite` is reserved
 * for a later WP and rejected here for now.
 */
final readonly class RegisterUserCommand
{
    public function __construct(
        public string $channel,
        public string $name,
        public ?string $areaId,
        public string $registeredVia,
        public ?string $phoneE164,
        public ?string $otp,
        public ?string $email,
        public ?string $password,
        public ?string $deviceName,
    ) {}
}
