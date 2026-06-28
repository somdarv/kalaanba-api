<?php

declare(strict_types=1);

namespace Kalaanba\Modules\Identity\Domain\EmailVerification;

use DateTimeImmutable;

/**
 * A freshly minted (or freshly looked-up) email verification token.
 *
 * The plaintext travels back to the caller exactly once at issuance and
 * is never re-readable from storage afterwards — only `tokenHash` is
 * persisted. Engineering-standards §11.
 */
final readonly class EmailVerificationToken
{
    public function __construct(
        public string $id,
        public string $userId,
        public string $email,
        public EmailVerificationPurpose $purpose,
        public string $tokenHash,
        public DateTimeImmutable $expiresAt,
        public ?DateTimeImmutable $consumedAt,
        public ?string $plaintext = null,
    ) {}

    public function isExpired(DateTimeImmutable $at): bool
    {
        return $at >= $this->expiresAt;
    }

    public function isConsumed(): bool
    {
        return $this->consumedAt !== null;
    }
}
