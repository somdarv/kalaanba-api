<?php

declare(strict_types=1);

namespace Kalaanba\Support\Auth\Otp;

/**
 * In-memory OTP provider used for development and the test suite.
 *
 * Captures the last (phone, code) tuple for assertion in tests. Engineering-
 * standards §10 forbids logging OTPs in any other provider; the mock is the
 * single allowed place an OTP exists in plain text outside the user's device.
 */
final class MockOtpProvider implements OtpProvider
{
    /** @var array{phone:string,code:string}|null */
    private ?array $lastSent = null;

    public function send(string $phoneE164, string $code): void
    {
        $this->lastSent = ['phone' => $phoneE164, 'code' => $code];
    }

    public function name(): string
    {
        return 'mock';
    }

    /**
     * @return array{phone:string,code:string}|null
     */
    public function lastSent(): ?array
    {
        return $this->lastSent;
    }

    public function reset(): void
    {
        $this->lastSent = null;
    }
}
