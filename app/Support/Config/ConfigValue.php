<?php

declare(strict_types=1);

namespace Kalaanba\Support\Config;

use DateTimeImmutable;

/**
 * Configuration value DTO — shared across all config readers.
 *
 * This DTO is safe for all modules to depend on; it lives in Support, not
 * inside AdminGovernance Domain, so it does not violate Deptrac layer boundaries.
 */
class ConfigValue
{
    public function __construct(
        public readonly string $key,
        public readonly string $scope,
        public readonly ?string $scopeId,
        public readonly string $value,
        public readonly DateTimeImmutable $effectiveFrom,
        public readonly int $version,
        public readonly ?string $approvedBy = null,
        public readonly string $approvalLevel = 'low',
        public readonly ?string $changeReason = null,
    ) {}

    /**
     * Cache key for this config value (including timestamp).
     */
    public function cacheKey(): string
    {
        return sprintf(
            'kx:config:v1:%s:%s:%s:%d',
            $this->key,
            $this->scope,
            $this->scopeId ?? 'none',
            $this->effectiveFrom->getTimestamp(),
        );
    }

    /**
     * Cache key pattern for this (key, scope, scope_id) quad (without timestamp).
     * Useful for pattern-based invalidation.
     */
    public function cacheKeyPattern(): string
    {
        return sprintf(
            'kx:config:v1:%s:%s:%s',
            $this->key,
            $this->scope,
            $this->scopeId ?? 'none',
        );
    }
}
