<?php

declare(strict_types=1);

namespace Kalaanba\Support\Auth\Otp;

use DateTimeImmutable;

/**
 * Immutable snapshot of an OTP record as held by an OtpStore.
 *
 * `codeHash` is the HMAC-SHA256 of the raw code under the application key —
 * we never persist the raw code anywhere, so a cache dump cannot replay.
 */
final readonly class OtpRecord
{
    public function __construct(
        public string $phoneHash,
        public string $codeHash,
        public int $attempts,
        public DateTimeImmutable $issuedAt,
        public DateTimeImmutable $expiresAt,
    ) {}

    public function withIncrementedAttempts(): self
    {
        return new self(
            $this->phoneHash,
            $this->codeHash,
            $this->attempts + 1,
            $this->issuedAt,
            $this->expiresAt,
        );
    }

    /**
     * @return array{phone_hash:string,code_hash:string,attempts:int,issued_at:int,expires_at:int}
     */
    public function toArray(): array
    {
        return [
            'phone_hash' => $this->phoneHash,
            'code_hash' => $this->codeHash,
            'attempts' => $this->attempts,
            'issued_at' => $this->issuedAt->getTimestamp(),
            'expires_at' => $this->expiresAt->getTimestamp(),
        ];
    }

    /**
     * @param  array{phone_hash:string,code_hash:string,attempts:int,issued_at:int,expires_at:int}  $payload
     */
    public static function fromArray(array $payload): self
    {
        return new self(
            $payload['phone_hash'],
            $payload['code_hash'],
            $payload['attempts'],
            (new DateTimeImmutable)->setTimestamp($payload['issued_at']),
            (new DateTimeImmutable)->setTimestamp($payload['expires_at']),
        );
    }
}
