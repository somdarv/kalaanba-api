<?php

declare(strict_types=1);

namespace Kalaanba\Support\Config\Contracts;

use DateTimeImmutable;
use Kalaanba\Support\Config\ConfigValue;

/**
 * Repository contract for config CRUD operations — shared across all modules.
 *
 * Supports effective-dating for time-travel reads (e.g., retroactive stats).
 */
interface ConfigRepository
{
    /**
     * Get a config value by key, scope, and optional effective date.
     *
     * @return ConfigValue|null if the key/scope combo has never been set
     */
    public function get(
        string $key,
        string $scope = 'platform',
        ?string $scopeId = null,
        ?DateTimeImmutable $at = null,
    ): ?ConfigValue;

    /**
     * Require a config value to be set; throw if missing.
     */
    public function require(
        string $key,
        string $scope = 'platform',
        ?string $scopeId = null,
        ?DateTimeImmutable $at = null,
    ): ConfigValue;

    /**
     * Retrieve all versions of a config key (for admin UIs showing history).
     *
     * @return list<ConfigValue> ordered by effective_from DESC (newest first)
     */
    public function history(string $key, string $scope = 'platform', ?string $scopeId = null): array;

    /**
     * Store a new version of a config value.
     *
     * The new row is assigned the next version number for this (key, scope, scope_id) tuple.
     * The old value remains in the table for audit trails and time-travel reads.
     *
     * @param  string  $key  The config key (e.g., 'rp.win')
     * @param  string  $value  The new value (stored as text)
     * @param  string  $scope  The scope ('platform', 'season', 'hub', 'venue', etc.)
     * @param  ?string  $scopeId  Optional scope ID (e.g., season UUID)
     * @param  ?string  $approvedBy  UUID of the user who approved this change
     * @param  string  $approvalLevel  Approval level ('low', 'medium', 'high', 'critical')
     * @param  ?string  $changeReason  Optional explanation of why the value changed
     * @return ConfigValue The newly created config value (with version incremented)
     */
    public function set(
        string $key,
        string $value,
        string $scope = 'platform',
        ?string $scopeId = null,
        ?string $approvedBy = null,
        string $approvalLevel = 'low',
        ?string $changeReason = null,
    ): ConfigValue;
}
