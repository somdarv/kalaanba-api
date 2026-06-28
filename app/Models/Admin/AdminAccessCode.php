<?php

declare(strict_types=1);

namespace App\Models\Admin;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $label
 * @property string $code_hash
 * @property Carbon|null $last_used_at
 */
class AdminAccessCode extends Model
{
    use HasUuids;

    /** The canonical label for the Users-section destructive-action gate. */
    public const USERS_SECTION = 'users_section';

    protected $fillable = ['label', 'code_hash', 'last_used_at'];

    protected $hidden = ['code_hash'];

    protected function casts(): array
    {
        return ['last_used_at' => 'datetime'];
    }
}
