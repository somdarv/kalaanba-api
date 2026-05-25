<?php

declare(strict_types=1);

namespace App\Models\Admin;

use App\Modules\AdminGovernance\Domain\Contracts\ConfigRepository;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * God Mode read+write surface for `admin_config`.
 *
 * Owned by the Admin Configuration Engine; this model is a thin Eloquent
 * shim for the Filament panel (ADR-0002). Production writes MUST go
 * through {@see ConfigRepository}
 * — Filament UI defers to a custom action that calls the repository.
 *
 * @property int $id
 * @property string $key
 * @property string $scope
 * @property string|null $scope_id
 * @property string $value
 * @property Carbon $effective_from
 * @property int $version
 * @property string|null $approved_by
 * @property string $approval_level
 * @property string|null $change_reason
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class AdminConfig extends Model
{
    protected $table = 'admin_config';

    protected $guarded = [];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'effective_from' => 'datetime',
            'version' => 'integer',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }
}
