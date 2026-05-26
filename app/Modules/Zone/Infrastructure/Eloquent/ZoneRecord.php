<?php

declare(strict_types=1);

namespace Kalaanba\Modules\Zone\Infrastructure\Eloquent;

use Illuminate\Database\Eloquent\Model;

class ZoneRecord extends Model
{
    protected $table = 'zones';

    public $incrementing = false;

    protected $keyType = 'string';

    public $timestamps = true;

    protected $guarded = [];
}
