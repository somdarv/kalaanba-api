<?php

declare(strict_types=1);

namespace Kalaanba\Modules\Season\Infrastructure\Eloquent;

use Illuminate\Database\Eloquent\Model;

/**
 * Eloquent record for the `seasons` table. Lives in Infrastructure so
 * the Domain port (`SeasonRepository`) can stay framework-free.
 */
final class SeasonRecord extends Model
{
    protected $table = 'seasons';

    public $incrementing = false;

    protected $keyType = 'string';

    public $timestamps = false;

    protected $guarded = [];

    protected $casts = [
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
