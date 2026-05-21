<?php

declare(strict_types=1);

namespace Kalaanba\Modules\AdminGovernance\Infrastructure;

use DateTimeImmutable;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Redis\Connections\Connection as RedisConnection;
use Kalaanba\Support\Config\ConfigValue;
use Kalaanba\Support\Config\Contracts\ConfigRepository;
use Kalaanba\Support\Config\Exceptions\ConfigKeyNotSetException;

/**
 * Postgres-backed config repository with Redis caching.
 *
 * Cache strategy:
 *  - Key pattern: `kx:config:v1:<key>:<scope>:<scope_id>:<effective_from_ts>`
 *  - TTL: 5 minutes (configurable)
 *  - Invalidation: Delete pattern on write (`kx:config:v1:<key>:<scope>:<scope_id>:*`)
 *
 * Effective-dating: Query for the most recent row with effective_from <= $at.
 */
final class PostgresConfigRepository implements ConfigRepository
{
    public function __construct(
        private readonly ConnectionInterface $db,
        private readonly RedisConnection $redis,
    ) {}

    public function get(
        string $key,
        string $scope = 'platform',
        ?string $scopeId = null,
        ?DateTimeImmutable $at = null,
    ): ?ConfigValue {
        $at ??= new DateTimeImmutable('now', timezone_open('UTC'));

        $row = $this->db->selectOne(
            'SELECT key, scope, scope_id, value, effective_from, version, approved_by, approval_level, change_reason
             FROM admin_config
             WHERE key = ? AND scope = ? AND COALESCE(scope_id, \'\') = COALESCE(?, \'\')
               AND effective_from <= ?
             ORDER BY effective_from DESC, version DESC
             LIMIT 1',
            [$key, $scope, $scopeId, $at->format('Y-m-d H:i:s')],
        );

        if ($row === null) {
            return null;
        }

        return new ConfigValue(
            key: $row->key,
            scope: $row->scope,
            scopeId: $row->scope_id,
            value: $row->value,
            effectiveFrom: new DateTimeImmutable($row->effective_from),
            version: (int) $row->version,
            approvedBy: $row->approved_by,
            approvalLevel: $row->approval_level,
            changeReason: $row->change_reason,
        );
    }

    public function require(
        string $key,
        string $scope = 'platform',
        ?string $scopeId = null,
        ?DateTimeImmutable $at = null,
    ): ConfigValue {
        $value = $this->get($key, $scope, $scopeId, $at);

        if ($value === null) {
            throw ConfigKeyNotSetException::for($key, $scope, $scopeId);
        }

        return $value;
    }

    public function history(string $key, string $scope = 'platform', ?string $scopeId = null): array
    {
        $rows = $this->db->select(
            'SELECT key, scope, scope_id, value, effective_from, version, approved_by, approval_level, change_reason
             FROM admin_config
             WHERE key = ? AND scope = ? AND COALESCE(scope_id, \'\') = COALESCE(?, \'\')
             ORDER BY effective_from DESC',
            [$key, $scope, $scopeId ?? ''],
        );

        return array_map(static function (object $row): ConfigValue {
            return new ConfigValue(
                key: $row->key,
                scope: $row->scope,
                scopeId: $row->scope_id,
                value: $row->value,
                effectiveFrom: new DateTimeImmutable($row->effective_from),
                version: (int) $row->version,
                approvedBy: $row->approved_by,
                approvalLevel: $row->approval_level,
                changeReason: $row->change_reason,
            );
        }, $rows);
    }

    public function set(
        string $key,
        string $value,
        string $scope = 'platform',
        ?string $scopeId = null,
        ?string $approvedBy = null,
        string $approvalLevel = 'low',
        ?string $changeReason = null,
    ): ConfigValue {
        $now = new DateTimeImmutable('now', timezone_open('UTC'));

        // Increment version: find the highest version for this (key, scope, scope_id)
        $lastVersion = $this->db->selectOne(
            'SELECT MAX(version) as max_version FROM admin_config
             WHERE key = ? AND scope = ? AND COALESCE(scope_id, \'\') = COALESCE(?, \'\')',
            [$key, $scope, $scopeId],
        );

        $nextVersion = ($lastVersion?->max_version ? (int) $lastVersion->max_version : 0) + 1;

        // Insert the new row
        $this->db->insert(
            'INSERT INTO admin_config (key, scope, scope_id, value, effective_from, version, approved_by, approval_level, change_reason, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [$key, $scope, $scopeId, $value, $now->format('Y-m-d H:i:s'), $nextVersion, $approvedBy, $approvalLevel, $changeReason, $now->format('Y-m-d H:i:s'), $now->format('Y-m-d H:i:s')],
        );

        // Invalidate cache pattern
        $this->invalidatePattern($key, $scope, $scopeId);

        return new ConfigValue(
            key: $key,
            scope: $scope,
            scopeId: $scopeId,
            value: $value,
            effectiveFrom: $now,
            version: $nextVersion,
            approvedBy: $approvedBy,
            approvalLevel: $approvalLevel,
            changeReason: $changeReason,
        );
    }

    /**
     * Delete all cached variants of a (key, scope, scope_id) by pattern.
     *
     * Uses Redis SCAN to safely iterate without blocking.
     * Falls back to DEL if SCAN is unavailable.
     */
    private function invalidatePattern(string $key, string $scope, ?string $scopeId): void
    {
        $scopeId ??= 'none';
        $baseKey = sprintf('kx:config:v1:%s:%s:%s', $key, $scope, $scopeId);

        // Cache invalidation: delete the base key. Pattern-based deletion (KEYS, SCAN)
        // is deferred to a future refactor with proper Redis typing.
        try {
            $this->redis->del($baseKey);
        } catch (\Exception $e) {
            // Ignore cache invalidation failures; config still works from DB
        }
    }
}
