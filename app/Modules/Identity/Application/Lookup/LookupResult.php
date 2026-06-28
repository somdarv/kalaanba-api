<?php

declare(strict_types=1);

namespace Kalaanba\Modules\Identity\Application\Lookup;

/**
 * Result of an account lookup: whether the identifier maps to an existing
 * active account, plus the channel inferred from the identifier's shape.
 *
 * Deliberately carries no PII (ADR-0004 §3, Identity doc §12) — only the
 * existence boolean and the channel key.
 */
final readonly class LookupResult
{
    public function __construct(
        public bool $exists,
        public string $channel,
    ) {}
}
