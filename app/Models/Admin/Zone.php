<?php

declare(strict_types=1);

namespace App\Models\Admin;

use Illuminate\Database\Eloquent\Model;

/** God Mode read surface for `zones`. Owned by Zone engine. */
class Zone extends Model
{
    protected $table = 'zones';

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
