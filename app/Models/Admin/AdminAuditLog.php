<?php

declare(strict_types=1);

namespace App\Models\Admin;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * God Mode read surface for `admin_audit_log`.
 *
 * Append-only audit ledger. NEVER edit, NEVER delete (Constitution L5).
 *
 * @property string $id
 * @property string $actor_id
 * @property string $actor_role
 * @property string $request_id
 * @property string|null $route
 * @property string $method
 * @property string $path
 * @property int $response_status
 * @property array<string,mixed> $payload_redacted
 * @property Carbon $occurred_at
 */
class AdminAuditLog extends Model
{
    protected $table = 'admin_audit_log';

    public $incrementing = false;

    protected $keyType = 'string';

    public $timestamps = false;

    protected $guarded = [];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'payload_redacted' => 'array',
            'occurred_at' => 'datetime',
            'response_status' => 'integer',
        ];
    }
}
