<?php

declare(strict_types=1);

namespace Kalaanba\Modules\Zone\Infrastructure\Eloquent;

use Illuminate\Database\Eloquent\Model;

class AreaSuggestionRecord extends Model
{
    protected $table = 'area_suggestions';

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
            'submitted_at' => 'datetime',
            'reviewed_at' => 'datetime',
        ];
    }
}
