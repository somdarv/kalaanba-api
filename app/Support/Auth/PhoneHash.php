<?php

declare(strict_types=1);

namespace Kalaanba\Support\Auth;

/**
 * Phone-number hashing utility.
 *
 * E.164 phone numbers are PII. We never store the raw value: only a
 * deterministic HMAC-SHA256 hash (for lookups + uniqueness) and the last
 * four digits (for "verify your number ends in 4567" UX).
 *
 * The HMAC secret is the application key. Rotating the app key would
 * invalidate every hash; rotation must be paired with a re-hash backfill.
 *
 * Engineering-standards §10 + §11: phone numbers MUST NOT appear in logs.
 */
final class PhoneHash
{
    public function __construct(private readonly string $secret) {}

    public function hash(string $phoneE164): string
    {
        return hash_hmac('sha256', $this->normalize($phoneE164), $this->secret);
    }

    public function last4(string $phoneE164): string
    {
        $digits = $this->digitsOnly($phoneE164);

        return substr($digits, -4);
    }

    public function mask(string $phoneE164): string
    {
        $normalized = $this->normalize($phoneE164);
        $last4 = $this->last4($normalized);
        $prefixLength = max(0, strlen($normalized) - 4);

        return str_repeat('*', $prefixLength).$last4;
    }

    private function normalize(string $phoneE164): string
    {
        $trimmed = trim($phoneE164);

        return $trimmed;
    }

    private function digitsOnly(string $value): string
    {
        return preg_replace('/\D+/', '', $value) ?? '';
    }
}
