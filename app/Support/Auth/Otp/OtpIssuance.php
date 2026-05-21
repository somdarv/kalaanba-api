<?php

declare(strict_types=1);

namespace Kalaanba\Support\Auth\Otp;

use DateTimeImmutable;

final readonly class OtpIssuance
{
    public function __construct(
        public DateTimeImmutable $expiresAt,
        public string $maskedPhone,
        public int $codeLength,
    ) {}
}
