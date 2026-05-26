<?php

declare(strict_types=1);

namespace Kalaanba\Modules\Zone\Infrastructure\Eloquent;

use Illuminate\Database\Eloquent\Model;

class CityHubRecord extends Model
{
    protected $table = 'city_hubs';

    public $incrementing = false;

    protected $keyType = 'string';

    public $timestamps = true;

    protected $guarded = [];
}
