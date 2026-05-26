<?php

declare(strict_types=1);

namespace App\Models\Admin;

use Illuminate\Database\Eloquent\Model;

/** God Mode read surface for `city_hubs`. Owned by Zone engine. */
class CityHub extends Model
{
    protected $table = 'city_hubs';

    public $incrementing = false;

    protected $keyType = 'string';

    public $timestamps = true;

    protected $guarded = [];

    /** @return array<string,string> */
    protected function casts(): array
    {
        return ['created_at' => 'datetime', 'updated_at' => 'datetime'];
    }
}
