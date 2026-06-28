<?php

declare(strict_types=1);

namespace Kalaanba\Modules\Identity\Domain\Events;

use DateTimeImmutable;
use Kalaanba\Modules\Identity\Domain\Registration\RegistrationChannel;

/**
 * Identity engine event — `identity.user_registered`.
 *
 * See contracts/events/identity/identity.user_registered.v1.yaml.
 * Engine doc: docs/engines/identity/Identity_Engine_System_Document.md §11.
 */
final readonly class UserRegistered
{
    public function __construct(
        public string $userId,
        public string $registeredVia,
        public RegistrationChannel $registeredChannel,
        public string $areaId,
        public string $name,
        public DateTimeImmutable $registeredAt,
    ) {}

    public const TOPIC = 'identity.user_registered';
}
