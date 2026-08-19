<?php

declare(strict_types=1);

namespace Kalaanba\Support\Auth\Otp;

use Illuminate\Contracts\Foundation\Application;

/**
 * In-memory OTP provider used for development and the test suite.
 *
 * Captures the last (phone, code) tuple for assertion in tests. Engineering-
 * standards §10 forbids logging OTPs in any other provider; the mock is the
 * single allowed place an OTP exists in plain text outside the user's device.
 *
 * For local development it also prints the code to the terminal + log (the
 * "log driver" sanctioned by Identity doc §4). This is gated strictly to the
 * `local` environment — it never runs in production or the test suite.
 */
final class MockOtpProvider implements OtpProvider
{
    /** @var array{phone:string,code:string}|null */
    private ?array $lastSent = null;

    public function send(string $phoneE164, string $code): void
    {
        $this->lastSent = ['phone' => $phoneE164, 'code' => $code];
        $this->announceForLocalDev($phoneE164, $code);
    }

    /** Dev-only: surface the OTP so a local tester can read it. Local env only. */
    private function announceForLocalDev(string $phoneE164, string $code): void
    {
        // Use the framework's resolved environment (reads config/app.env, so it
        // is correct even when the config is cached) rather than raw getenv.
        //
        // The instanceof is load-bearing, not defensive noise: in a Pest *unit*
        // test nothing boots the framework, so `app()` hands back a bare
        // Container, which has no environment() and fatals. Checking the type
        // rather than just function_exists('app') keeps this provider usable
        // from a plain unit test — which is exactly where it is meant to be used.
        if (! function_exists('app') || ! app() instanceof Application) {
            return;
        }

        if (! app()->environment('local')) {
            return;
        }

        $line = sprintf('[DEV OTP] %s -> %s', $phoneE164, $code);
        if (defined('STDERR')) {
            fwrite(STDERR, PHP_EOL.$line.PHP_EOL);
        }
        logger()->info($line);
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
