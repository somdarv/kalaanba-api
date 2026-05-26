<?php

declare(strict_types=1);

namespace Kalaanba\Modules\Zone\Infrastructure\Eloquent;

use Illuminate\Database\Eloquent\Model;

class CountryRecord extends Model
{
    protected $table = 'countries';

    public $incrementing = false;

    protected $keyType = 'string';

    public $timestamps = true;

    protected $guarded = [];
}
