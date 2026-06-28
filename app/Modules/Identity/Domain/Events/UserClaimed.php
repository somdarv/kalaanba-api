<?php

declare(strict_types=1);

namespace Kalaanba\Modules\Identity\Domain\Events;

use DateTimeImmutable;
use Kalaanba\Modules\Identity\Domain\Registration\RegistrationChannel;

/**
 * Identity engine event — `identity.user_claimed`.
 *
 * Fires on PENDING_CLAIM → CLAIMED transition only. In WP-20260530 this
 * is exclusively the email-verify confirmation path; phone signups skip
 * this event because they enter CLAIMED at creation time.
 *
 * See contracts/events/identity/identity.user_claimed.v1.yaml.
 */
final readonly class UserClaimed
{
    public function __construct(
        public string $userId,
        public string $claimedVia,
        public RegistrationChannel $claimedChannel,
        public DateTimeImmutable $claimedAt,
    ) {}

    public const TOPIC = 'identity.user_claimed';
}
