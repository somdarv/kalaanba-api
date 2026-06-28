<?php

declare(strict_types=1);

namespace Kalaanba\Modules\Identity\Application\EmailVerification;

use Kalaanba\Modules\Identity\Domain\EmailVerification\EmailVerificationPurpose;

/**
 * Outcome of a successful {@see ConfirmEmailHandler::handle()} call.
 *
 * `purpose === Registration` → Http layer mints a Sanctum token and
 * returns a SessionResponse to the contract.
 * `purpose === BindEmail`    → Http layer returns the refreshed Me view.
 */
final readonly class ConfirmEmailResult
{
    public function __construct(
        public string $userId,
        public string $email,
        public EmailVerificationPurpose $purpose,
    ) {}
}
