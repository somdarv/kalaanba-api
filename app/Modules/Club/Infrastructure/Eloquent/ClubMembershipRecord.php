<?php

declare(strict_types=1);

namespace Kalaanba\Modules\Club\Infrastructure\Eloquent;

use Illuminate\Database\Eloquent\Model;

class ClubMembershipRecord extends Model
{
    protected $table = 'club_memberships';

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
            'archived_at' => 'datetime',
        ];
    }
}
