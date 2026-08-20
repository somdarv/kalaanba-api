<?php

declare(strict_types=1);

namespace Kalaanba\Modules\PlayerAffiliation\Infrastructure\Eloquent;

use Illuminate\Database\Eloquent\Model;

class PlayerRecord extends Model
{
    protected $table = 'players';

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
            'preferred_number' => 'integer',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
            'archived_at' => 'datetime',
        ];
    }
}
