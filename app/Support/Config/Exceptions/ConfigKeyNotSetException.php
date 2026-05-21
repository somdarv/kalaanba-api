<?php

declare(strict_types=1);

namespace Kalaanba\Support\Config\Exceptions;

use RuntimeException;

/**
 * Thrown when Config::require() is called for a key that has never been set.
 */
class ConfigKeyNotSetException extends RuntimeException
{
    public static function for(string $key, string $scope = 'platform', ?string $scopeId = null): self
    {
        $scopeId = $scopeId ?? '(default)';

        return new self(
            "Config key '{$key}' (scope: {$scope}, scope_id: {$scopeId}) has never been set.",
        );
    }
}
