<?php

declare(strict_types=1);

namespace Kalaanba\Modules\Identity\Domain\Registration;

/**
 * Framework-free password validator. All thresholds are passed in by the
 * application layer (read from `auth.password.*` config keys). The Domain
 * itself never reads a facade or constant — Constitution §1.2.
 *
 * Returned violation codes match the public API contract:
 *   - auth.password.too_short
 *   - auth.password.require_mixed_case
 *   - auth.password.require_number
 *   - auth.password.require_symbol
 */
final readonly class PasswordPolicy
{
    public function __construct(
        public int $minLength,
        public bool $requireMixedCase,
        public bool $requireNumber,
        public bool $requireSymbol,
    ) {}

    /**
     * @return list<string> Empty list on success; violation codes otherwise.
     */
    public function evaluate(string $plain): array
    {
        $violations = [];

        if (mb_strlen($plain) < $this->minLength) {
            $violations[] = 'auth.password.too_short';
        }

        if ($this->requireMixedCase && (preg_match('/[a-z]/', $plain) !== 1 || preg_match('/[A-Z]/', $plain) !== 1)) {
            $violations[] = 'auth.password.require_mixed_case';
        }

        if ($this->requireNumber && preg_match('/[0-9]/', $plain) !== 1) {
            $violations[] = 'auth.password.require_number';
        }

        if ($this->requireSymbol && preg_match('/[^A-Za-z0-9]/', $plain) !== 1) {
            $violations[] = 'auth.password.require_symbol';
        }

        return $violations;
    }
}
