<?php

declare(strict_types=1);

namespace Kalaanba\Modules\Identity\Application\Registration;

use DateTimeImmutable;

/**
 * Result of {@see RegisterUserHandler}.
 *
 * Phone signups complete in one step → `phone` channel carries a
 * `userId` and `claimedAt`. The Http layer reads these and mints a
 * Sanctum token outside the Domain.
 *
 * Email signups return a pending verification → `email` channel carries
 * a `userId`, the issued token's `expiresAt`, and optionally the
 * plaintext `verificationToken` (ONLY when the Notification driver is
 * the log/dev driver, so tests + local QA can complete the flow without
 * a real mail provider).
 */
final readonly class RegisterUserResult
{
    private function __construct(
        public string $channel,
        public string $userId,
        public ?DateTimeImmutable $claimedAt,
        public ?DateTimeImmutable $verificationExpiresAt,
        public ?string $verificationToken,
    ) {}

    public static function phoneClaimed(string $userId, DateTimeImmutable $claimedAt): self
    {
        return new self('phone', $userId, $claimedAt, null, null);
    }

    public static function emailPendingVerification(
        string $userId,
        DateTimeImmutable $expiresAt,
        ?string $verificationToken,
    ): self {
        return new self('email', $userId, null, $expiresAt, $verificationToken);
    }

    public function isPhone(): bool
    {
        return $this->channel === 'phone';
    }
}
