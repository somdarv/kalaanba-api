<?php

declare(strict_types=1);

namespace Kalaanba\Support;

use DateTimeImmutable;
use Illuminate\Support\Facades\Facade;
use Kalaanba\Support\Config\ConfigValue;
use Kalaanba\Support\Config\Contracts\ConfigRepository;
use Kalaanba\Support\Config\Exceptions\ConfigKeyNotSetException;

/**
 * Facade for reading and writing configuration values.
 *
 * Usage:
 *   $rp = Config::get('rp.win', 'platform'); // returns ConfigValue
 *   $value = Config::get('rp.win', 'platform')?->value; // nullable
 *   $value = Config::require('rp.win', 'platform'); // throws if not set
 *   $historicalValue = Config::get('rp.win', 'platform', scopeId: null, at: $someDate); // time-travel
 *   Config::set('rp.win', '25', 'platform', reason: 'Raised for Season 2026'); // new version
 *
 * @method static ConfigValue|null get(string $key, string $scope = 'platform', ?string $scopeId = null, ?DateTimeImmutable $at = null)
 * @method static ConfigValue require(string $key, string $scope = 'platform', ?string $scopeId = null, ?DateTimeImmutable $at = null)
 * @method static list<ConfigValue> history(string $key, string $scope = 'platform', ?string $scopeId = null)
 * @method static ConfigValue set(string $key, string $value, string $scope = 'platform', ?string $scopeId = null, ?string $approvedBy = null, string $approvalLevel = 'low', ?string $changeReason = null)
 *
 * @see ConfigRepository
 */
class Config extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return ConfigRepository::class;
    }

    /**
     * Require a config value to be set; throw if not.
     */
    public static function require(
        string $key,
        string $scope = 'platform',
        ?string $scopeId = null,
        ?DateTimeImmutable $at = null,
    ): ConfigValue {
        $value = static::get($key, $scope, $scopeId, $at);

        if ($value === null) {
            throw ConfigKeyNotSetException::for($key, $scope, $scopeId);
        }

        return $value;
    }
}
