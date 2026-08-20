<?php

declare(strict_types=1);

namespace Kalaanba\Modules\Club\Infrastructure\Eloquent;

use Illuminate\Database\Eloquent\Model;

class ClubRecord extends Model
{
    protected $table = 'clubs';

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
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
            'archived_at' => 'datetime',
        ];
    }
}
