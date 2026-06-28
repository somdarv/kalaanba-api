<?php

declare(strict_types=1);

namespace Kalaanba\Support\Audit;

/**
 * Strips secrets / OTPs / passwords / tokens out of a request payload before
 * it is persisted to the audit log. Engineering-standards §10 / §11.
 *
 * The redactor walks the payload recursively and replaces matching key
 * VALUES with a constant marker — keys are preserved so investigators can
 * still see WHAT field was touched, just not its value.
 */
final class PayloadRedactor
{
    public const REDACTED = '[redacted]';

    /**
     * Case-insensitive substring matches against the JSON key. Any key
     * containing one of these tokens has its value replaced.
     */
    private const SENSITIVE_KEY_TOKENS = [
        'password',
        'token',
        'secret',
        'otp',
        'access_code',
        'authorization',
        'cookie',
        'api_key',
        'apikey',
        'pin',
        'cvv',
        'phone_e164',
    ];

    /**
     * @param  array<array-key,mixed>  $payload
     * @return array<array-key,mixed>
     */
    public function redact(array $payload): array
    {
        $out = [];
        foreach ($payload as $key => $value) {
            if (is_string($key) && $this->isSensitive($key)) {
                $out[$key] = self::REDACTED;

                continue;
            }
            $out[$key] = is_array($value) ? $this->redact($value) : $value;
        }

        return $out;
    }

    private function isSensitive(string $key): bool
    {
        $needle = strtolower($key);
        foreach (self::SENSITIVE_KEY_TOKENS as $token) {
            if (str_contains($needle, $token)) {
                return true;
            }
        }

        return false;
    }
}
