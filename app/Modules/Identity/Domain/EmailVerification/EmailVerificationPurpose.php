<?php

declare(strict_types=1);

namespace Kalaanba\Modules\Identity\Domain\EmailVerification;

/**
 * Purpose of an email verification token. Drives the consumed-by-handler
 * dispatch (registration confirms an account; bind_email attaches an email
 * to an already-CLAIMED user).
 */
enum EmailVerificationPurpose: string
{
    case Registration = 'registration';
    case BindEmail = 'bind_email';
}
