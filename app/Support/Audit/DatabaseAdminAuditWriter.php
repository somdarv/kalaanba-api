<?php

declare(strict_types=1);

namespace Kalaanba\Support\Audit;

use Illuminate\Database\ConnectionInterface;

final class DatabaseAdminAuditWriter implements AdminAuditWriter
{
    public function __construct(
        private readonly ConnectionInterface $connection,
        private readonly string $table = 'admin_audit_log',
    ) {}

    public function write(AdminAuditEntry $entry): void
    {
        $this->connection->table($this->table)->insert([
            'id' => $entry->id,
            'actor_id' => $entry->actorId,
            'actor_role' => $entry->actorRole,
            'request_id' => $entry->requestId,
            'route' => $entry->route,
            'method' => $entry->method,
            'path' => $entry->path,
            'response_status' => $entry->responseStatus,
            'payload_redacted' => json_encode($entry->payloadRedacted, JSON_THROW_ON_ERROR),
            'occurred_at' => $entry->occurredAt->format('Y-m-d H:i:s.uP'),
        ]);
    }
}
