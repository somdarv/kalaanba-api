<?php

declare(strict_types=1);

namespace Kalaanba\Modules\Identity\Application\EmailVerification;

use Kalaanba\Modules\Identity\Domain\EmailVerification\EmailVerificationToken;

/**
 * Persistence port for issued + consumed email verification tokens.
 *
 * Plaintext tokens are NEVER stored; the adapter persists only the
 * SHA-256 hash. See migration `2026_05_30_000002_create_email_verifications_table`.
 */
interface EmailVerificationRepository
{
    /**
     * Persist a freshly issued token. `$token->plaintext` is the only
     * place the plaintext exists for the lifetime of the request.
     */
    public function issue(EmailVerificationToken $token): void;

    /**
     * Look up by plaintext token. Returns null when no row matches the
     * SHA-256 hash. Consumed + expired rows are still returned so the
     * Application layer can surface the specific error code.
     */
    public function findByPlaintext(string $plaintext): ?EmailVerificationToken;

    /**
     * Mark the token row as consumed. Idempotent: calling twice on the
     * same id is a no-op (the SQL UPDATE includes `consumed_at IS NULL`).
     */
    public function consume(string $tokenId, \DateTimeImmutable $consumedAt): void;
}
