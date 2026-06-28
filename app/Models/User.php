<?php

declare(strict_types=1);

namespace App\Models;

use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Kalaanba\Support\Auth\Role;
use Laravel\Sanctum\HasApiTokens;

/**
 * @property string $id
 * @property string $name
 * @property string $email
 * @property string $password
 * @property Role $role
 * @property string|null $phone_e164_hash
 * @property string|null $phone_e164_last4
 * @property string|null $area_id
 * @property string|null $avatar_url
 * @property Carbon|null $email_verified_at
 * @property Carbon|null $archived_at
 * @property Carbon|null $disabled_at
 * @property Carbon|null $claimed_at
 * @property Carbon|null $last_seen_at
 * @property-read bool $is_active
 */
class User extends Authenticatable implements FilamentUser
{
    use HasApiTokens;
    use HasFactory;
    use HasUuids;
    use Notifiable;

    /**
     * Mass-assignment is intentionally narrow. Role + archive timestamps are
     * set explicitly through application services — no controller may flip
     * a role via request payload (engineering-standards §11).
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'phone_e164_hash',
        'phone_e164_last4',
        'area_id',
        'avatar_url',
    ];

    /**
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        'phone_e164_hash',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'archived_at' => 'datetime',
            'disabled_at' => 'datetime',
            'claimed_at' => 'datetime',
            'last_seen_at' => 'datetime',
            'password' => 'hashed',
            'role' => Role::class,
        ];
    }

    protected function isActive(): Attribute
    {
        return Attribute::get(
            fn (): bool => $this->archived_at === null && $this->disabled_at === null,
        );
    }

    /**
     * Filament panel gate (ADR-0002): only Super Admins access `/admin`.
     *
     * Archived users are also denied even if they hold the Super Admin role,
     * matching the engineering-standards L11 “archive, don't delete” rule —
     * archive is the kill-switch.
     */
    public function canAccessPanel(Panel $panel): bool
    {
        if ($panel->getId() !== 'admin') {
            return false;
        }

        if ($this->archived_at !== null) {
            return false;
        }

        return $this->role->isSuperAdmin();
    }
}
