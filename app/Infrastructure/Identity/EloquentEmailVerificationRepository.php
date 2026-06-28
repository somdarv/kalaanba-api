<?php

declare(strict_types=1);

namespace App\Infrastructure\Identity;

use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Support\Facades\DB;
use Kalaanba\Modules\Identity\Application\EmailVerification\EmailVerificationRepository;
use Kalaanba\Modules\Identity\Domain\EmailVerification\EmailVerificationPurpose;
use Kalaanba\Modules\Identity\Domain\EmailVerification\EmailVerificationToken;
use stdClass;

/**
 * Adapter for the `email_verifications` table.
 *
 * Schema (see migration `2026_05_30_000002_create_email_verifications_table`):
 *   id, user_id, email, token_hash, purpose, expires_at, consumed_at, created_at.
 *
 * Plaintext is NEVER persisted — only the SHA-256 hash. The plaintext
 * lives on the returned VO only during the request that issued it.
 *
 * Lives in App\Infrastructure\Identity since it talks to DB directly via
 * the query builder (architecturally identical to {@see EloquentUserRegistrationRepository}).
 */
final readonly class EloquentEmailVerificationRepository implements EmailVerificationRepository
{
    private const TABLE = 'email_verifications';

    public function issue(EmailVerificationToken $token): void
    {
        DB::table(self::TABLE)->insert([
            'id' => $token->id,
            'user_id' => $token->userId,
            'email' => $token->email,
            'token_hash' => $token->tokenHash,
            'purpose' => $token->purpose->value,
            'expires_at' => $token->expiresAt,
            'consumed_at' => null,
            'created_at' => new DateTimeImmutable('now', new DateTimeZone('UTC')),
        ]);
    }

    public function findByPlaintext(string $plaintext): ?EmailVerificationToken
    {
        $row = DB::table(self::TABLE)
            ->where('token_hash', hash('sha256', $plaintext))
            ->first();

        if ($row === null) {
            return null;
        }

        return $this->hydrate($row);
    }

    public function consume(string $tokenId, DateTimeImmutable $consumedAt): void
    {
        DB::table(self::TABLE)
            ->where('id', $tokenId)
            ->whereNull('consumed_at')
            ->update(['consumed_at' => $consumedAt]);
    }

    private function hydrate(stdClass $row): EmailVerificationToken
    {
        return new EmailVerificationToken(
            id: (string) $row->id,
            userId: (string) $row->user_id,
            email: (string) $row->email,
            purpose: EmailVerificationPurpose::from((string) $row->purpose),
            tokenHash: (string) $row->token_hash,
            expiresAt: $this->toDateTime($row->expires_at),
            consumedAt: $row->consumed_at === null ? null : $this->toDateTime($row->consumed_at),
        );
    }

    private function toDateTime(mixed $value): DateTimeImmutable
    {
        if ($value instanceof DateTimeImmutable) {
            return $value->setTimezone(new DateTimeZone('UTC'));
        }

        return new DateTimeImmutable((string) $value, new DateTimeZone('UTC'));
    }
}
