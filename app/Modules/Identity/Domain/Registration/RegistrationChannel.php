<?php

declare(strict_types=1);

namespace Kalaanba\Modules\Identity\Domain\Registration;

/**
 * Channel through which a user registered.
 *
 * See docs/engines/identity/Identity_Engine_System_Document.md §7.1.
 */
enum RegistrationChannel: string
{
    case Phone = 'phone';
    case Email = 'email';
}
