<?php

declare(strict_types=1);

namespace App\Services\Admin;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * Read-side of the admin Users section: a searchable, filterable, paginated
 * directory plus a per-user detail projection.
 *
 * Every projection is **admin-safe** — it carries no password, no password
 * hash, no OTP, and never the full phone number (only the stored last-4).
 * Identity doc §12 / engineering-standards §10.
 */
final class AdminUserDirectory
{
    private const MAX_PER_PAGE = 100;

    /**
     * @param  array<string,mixed>  $filters
     * @return array{data: array<int,array<string,mixed>>, meta: array<string,mixed>}
     */
    public function list(array $filters): array
    {
        $perPage = min((int) ($filters['per_page'] ?? 25) ?: 25, self::MAX_PER_PAGE);
        $sort = in_array($filters['sort'] ?? '', ['created_at', 'last_seen_at'], true)
            ? (string) $filters['sort']
            : 'created_at';

        $query = User::query()
            ->when(
                isset($filters['search']) && $filters['search'] !== '',
                fn (Builder $q) => $this->applySearch($q, (string) $filters['search']),
            )
            ->when(
                isset($filters['status']) && $filters['status'] !== '',
                fn (Builder $q) => $this->applyStatus($q, (string) $filters['status']),
            )
            ->when(
                ($filters['auth_method'] ?? '') === 'phone',
                fn (Builder $q) => $q->whereNotNull('phone_e164_hash'),
            )
            ->when(
                ($filters['auth_method'] ?? '') === 'email',
                fn (Builder $q) => $q->whereNotNull('email'),
            )
            ->orderByDesc($sort);

        $page = $query->paginate($perPage);

        return [
            'data' => array_map(
                fn (User $user): array => $this->present($user),
                $page->items(),
            ),
            'meta' => [
                'page' => $page->currentPage(),
                'per_page' => $page->perPage(),
                'total' => $page->total(),
                'last_page' => $page->lastPage(),
            ],
        ];
    }

    /** @return array<string,mixed> */
    public function present(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'phone_masked' => $this->maskPhone($user->phone_e164_last4),
            'auth_method' => $this->authMethod($user),
            'status' => $this->status($user),
            'phone_verified' => $user->phone_e164_hash !== null && $user->claimed_at !== null,
            'email_verified' => $user->email_verified_at !== null,
            'role' => $user->role->value,
            'created_at' => $user->created_at?->toIso8601String(),
            'last_seen_at' => $user->last_seen_at?->toIso8601String(),
        ];
    }

    /**
     * @param  Builder<User>  $query
     * @return Builder<User>
     */
    private function applySearch(Builder $query, string $term): Builder
    {
        $last4 = substr(preg_replace('/\D/', '', $term) ?? '', -4);

        return $query->where(function (Builder $q) use ($term, $last4): void {
            $q->where('name', 'like', "%{$term}%")
                ->orWhere('email', 'like', "%{$term}%");
            if ($last4 !== '') {
                $q->orWhere('phone_e164_last4', $last4);
            }
        });
    }

    /**
     * @param  Builder<User>  $query
     * @return Builder<User>
     */
    private function applyStatus(Builder $query, string $status): Builder
    {
        return match ($status) {
            'disabled' => $query->whereNotNull('disabled_at'),
            'archived' => $query->whereNotNull('archived_at'),
            'unverified' => $query->whereNull('claimed_at')->whereNull('archived_at'),
            'active' => $query->whereNull('archived_at')
                ->whereNull('disabled_at')
                ->whereNotNull('claimed_at'),
            default => $query,
        };
    }

    private function status(User $user): string
    {
        return match (true) {
            $user->archived_at !== null => 'archived',
            $user->disabled_at !== null => 'disabled',
            $user->claimed_at === null => 'unverified',
            default => 'active',
        };
    }

    private function authMethod(User $user): string
    {
        $hasPhone = $user->phone_e164_hash !== null;
        $hasEmail = $user->email !== null;

        return match (true) {
            $hasPhone && $hasEmail => 'both',
            $hasPhone => 'phone',
            default => 'email',
        };
    }

    private function maskPhone(?string $last4): ?string
    {
        return $last4 === null ? null : "••• ••• {$last4}";
    }
}
