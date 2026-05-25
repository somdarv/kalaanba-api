<?php

declare(strict_types=1);

namespace App\Models\Admin;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * God Mode read surface for `analytics.events` (partitioned).
 *
 * READ-ONLY. Analytics is a downstream consumer (Constitution L6 — event-first);
 * never let the panel write here directly.
 *
 * @property string $id
 * @property string $event_id
 * @property string $event_name
 * @property int $schema_version
 * @property Carbon $occurred_at
 * @property string|null $actor_user_id
 * @property string|null $actor_role
 * @property string $source
 * @property array<string,mixed> $context
 * @property array<string,mixed> $properties
 */
class AnalyticsEvent extends Model
{
    protected $table = 'analytics.events';

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
            'occurred_at' => 'datetime',
            'received_at' => 'datetime',
            'context' => 'array',
            'properties' => 'array',
            'schema_version' => 'integer',
        ];
    }
}
