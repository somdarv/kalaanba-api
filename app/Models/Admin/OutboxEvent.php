<?php

declare(strict_types=1);

namespace App\Models\Admin;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * God Mode read surface for `outbox_events`.
 *
 * The outbox is OWNED by the EventBus support layer, not by any engine
 * (it's the cross-engine plumbing). This model exists only to power the
 * Filament panel — engine code MUST NOT depend on it (ADR-0002).
 *
 * @property string $id
 * @property string $event_id
 * @property string $event_name
 * @property int $schema_version
 * @property array<string,mixed> $payload
 * @property Carbon $occurred_at
 * @property Carbon|null $delivered_at
 * @property int $attempts
 * @property string|null $last_error
 * @property Carbon $created_at
 */
class OutboxEvent extends Model
{
    protected $table = 'outbox_events';

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
            'payload' => 'array',
            'occurred_at' => 'datetime',
            'delivered_at' => 'datetime',
            'created_at' => 'datetime',
            'schema_version' => 'integer',
            'attempts' => 'integer',
        ];
    }
}
