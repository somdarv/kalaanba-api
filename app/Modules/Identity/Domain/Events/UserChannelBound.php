<?php

declare(strict_types=1);

namespace Kalaanba\Modules\Identity\Domain\Events;

use DateTimeImmutable;
use Kalaanba\Modules\Identity\Domain\Registration\RegistrationChannel;

/**
 * Identity engine event — `identity.user_channel_bound`.
 *
 * Fires when an authenticated user adds a second channel (phone-only adds
 * email, or email-only adds phone). Distinct from `user_claimed` — the
 * user was already CLAIMED before this transition.
 *
 * See contracts/events/identity/identity.user_channel_bound.v1.yaml.
 */
final readonly class UserChannelBound
{
    public function __construct(
        public string $userId,
        public RegistrationChannel $channel,
        public DateTimeImmutable $boundAt,
    ) {}

    public const TOPIC = 'identity.user_channel_bound';
}
