<?php

declare(strict_types=1);

namespace App\Models\Admin;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * God Mode read surface for `seasons`.
 *
 * The table is OWNED by the Season engine (`Kalaanba\Modules\Season`); this
 * model is for the Filament admin panel ONLY. Per ADR-0002, engine code MUST
 * NOT depend on `App\Models\Admin\*` — they reach into the data they own via
 * their own infrastructure layer.
 *
 * @property string $id
 * @property string $code
 * @property Carbon $starts_at
 * @property Carbon $ends_at
 * @property Carbon $participation_cutoff_at
 * @property Carbon $closing_window_ends_at
 * @property Carbon $archive_window_ends_at
 * @property string $phase
 * @property array<string,mixed>|null $key_dates
 * @property Carbon|null $archived_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class Season extends Model
{
    protected $table = 'seasons';

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
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'participation_cutoff_at' => 'datetime',
            'closing_window_ends_at' => 'datetime',
            'archive_window_ends_at' => 'datetime',
            'archived_at' => 'datetime',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
            'key_dates' => 'array',
        ];
    }
}
