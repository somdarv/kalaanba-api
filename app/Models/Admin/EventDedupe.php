<?php

declare(strict_types=1);

namespace App\Models\Admin;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * God Mode read surface for `event_dedupe` — listener-side idempotency state.
 *
 * READ-ONLY for the panel. Stored as a composite key (event_id, listener_name).
 * Filament needs a primary-key style; we expose the natural composite via a
 * computed `id` accessor.
 *
 * @property string $event_id
 * @property string $listener_name
 * @property Carbon $processed_at
 * @property-read string $id
 */
class EventDedupe extends Model
{
    protected $table = 'event_dedupe';

    public $incrementing = false;

    protected $primaryKey = 'event_id';

    protected $keyType = 'string';

    public $timestamps = false;

    protected $guarded = [];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'processed_at' => 'datetime',
        ];
    }
}
