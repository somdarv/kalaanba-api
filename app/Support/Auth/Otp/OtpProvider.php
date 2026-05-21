<?php

declare(strict_types=1);

namespace Kalaanba\Support\Auth\Otp;

/**
 * Delivery channel for OTP codes.
 *
 * Selected at runtime via the `auth.otp_provider` config key.
 * MockOtpProvider captures the last code for tests; WhatsAppOtpProvider
 * ships in Build Plan Phase 4.
 */
interface OtpProvider
{
    public function send(string $phoneE164, string $code): void;

    /**
     * Stable identifier for logging + observability. MUST match an allowed
     * value of the `auth.otp_provider` config key.
     */
    public function name(): string;
}
