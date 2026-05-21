<?php

declare(strict_types=1);

namespace Kalaanba\Support\Auth\Otp;

use DateTimeImmutable;
use Kalaanba\Support\Auth\Otp\Exceptions\OtpAttemptsExhaustedException;
use Kalaanba\Support\Auth\Otp\Exceptions\OtpExpiredException;
use Kalaanba\Support\Auth\Otp\Exceptions\OtpInvalidException;
use Kalaanba\Support\Auth\Otp\Exceptions\OtpNotFoundException;
use Kalaanba\Support\Auth\PhoneHash;
use Psr\Clock\ClockInterface;

/**
 * Issuance + verification of phone-bound OTPs.
 *
 * All values that affect behaviour are read at construction time from the
 * Admin Configuration registry — `auth.otp_ttl_seconds`, `auth.otp_length`,
 * `auth.otp_max_attempts`. No magic numbers (Constitution Law 2).
 *
 * Codes are persisted only as HMAC hashes (`auth.otp_provider` is the only
 * place a plain-text code exists once it leaves this service).
 */
final class OtpService
{
    public function __construct(
        private readonly OtpStore $store,
        private readonly OtpProvider $provider,
        private readonly PhoneHash $phoneHash,
        private readonly ClockInterface $clock,
        private readonly CodeGenerator $codeGenerator,
        private readonly string $codeSecret,
        private readonly int $ttlSeconds,
        private readonly int $codeLength,
        private readonly int $maxAttempts,
    ) {}

    /**
     * Generate a fresh OTP, persist it, hand it to the active provider,
     * and return the issuance metadata (no plaintext code escapes).
     */
    public function issue(string $phoneE164): OtpIssuance
    {
        $code = $this->generateCode();
        $phoneHash = $this->phoneHash->hash($phoneE164);
        $now = $this->now();
        $expiresAt = $now->modify(sprintf('+%d seconds', $this->ttlSeconds));

        $record = new OtpRecord(
            phoneHash: $phoneHash,
            codeHash: $this->hashCode($code),
            attempts: 0,
            issuedAt: $now,
            expiresAt: $expiresAt,
        );

        $this->store->put($record, $this->ttlSeconds);
        $this->provider->send($phoneE164, $code);

        return new OtpIssuance(
            expiresAt: $expiresAt,
            maskedPhone: $this->phoneHash->mask($phoneE164),
            codeLength: $this->codeLength,
        );
    }

    /**
     * Verify a submitted code against the stored record. On success the
     * record is consumed (single-use). On failure either the attempt count
     * is incremented or the record is invalidated.
     *
     * @throws OtpNotFoundException|OtpExpiredException|OtpInvalidException|OtpAttemptsExhaustedException
     */
    public function verify(string $phoneE164, string $submittedCode): void
    {
        $phoneHash = $this->phoneHash->hash($phoneE164);
        $record = $this->store->find($phoneHash);

        if ($record === null) {
            throw new OtpNotFoundException;
        }

        if ($this->now() >= $record->expiresAt) {
            $this->store->forget($phoneHash);
            throw new OtpExpiredException;
        }

        if (hash_equals($record->codeHash, $this->hashCode($submittedCode))) {
            $this->store->forget($phoneHash);

            return;
        }

        $incremented = $record->withIncrementedAttempts();

        if ($incremented->attempts >= $this->maxAttempts) {
            $this->store->forget($phoneHash);
            throw new OtpAttemptsExhaustedException;
        }

        $this->store->put($incremented, $this->ttlSeconds);
        throw new OtpInvalidException;
    }

    private function generateCode(): string
    {
        return $this->codeGenerator->generate($this->codeLength);
    }

    private function hashCode(string $code): string
    {
        return hash_hmac('sha256', $code, $this->codeSecret);
    }

    private function now(): DateTimeImmutable
    {
        return DateTimeImmutable::createFromInterface($this->clock->now());
    }
}
