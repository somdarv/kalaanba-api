<?php

declare(strict_types=1);

namespace App\Infrastructure\Identity;

use App\Models\User;
use DateTimeImmutable;
use Illuminate\Database\QueryException;
use Kalaanba\Modules\Identity\Application\Registration\DuplicateChannelException;
use Kalaanba\Modules\Identity\Application\Registration\NewUserRegistration;
use Kalaanba\Modules\Identity\Application\Registration\UserRegistrationRepository;
use Kalaanba\Support\Auth\Role;

/**
 * Eloquent adapter for {@see UserRegistrationRepository}.
 *
 * Lives in App\Infrastructure\Identity (not the engine module) because it
 * bridges `App\Models\User` — engine modules are forbidden from touching
 * App\Models directly (Pest architecture rule).
 *
 * Unique-violation handling: Postgres returns SQLSTATE 23505. We translate
 * to {@see DuplicateChannelException} so the Application layer can map to
 * a 409 with the stable error code. SQLite signals the same logical
 * collision via SQLSTATE 23000, also caught.
 */
final readonly class EloquentUserRegistrationRepository implements UserRegistrationRepository
{
    public function create(NewUserRegistration $registration): string
    {
        try {
            $user = new User;
            $user->forceFill([
                'id' => $registration->id,
                'name' => $registration->name,
                'email' => $registration->email,
                'password' => $registration->passwordHash,
                'phone_e164_hash' => $registration->phoneE164Hash,
                'area_id' => $registration->areaId,
                'role' => Role::from($registration->role),
                'claimed_at' => $registration->claimedAt,
                'email_verified_at' => $registration->email !== null && $registration->claimedAt !== null
                    ? $registration->claimedAt
                    : null,
                'created_at' => $registration->createdAt,
                'updated_at' => $registration->createdAt,
            ])->save();
        } catch (QueryException $e) {
            throw $this->translateDuplicate($e, $registration->email !== null ? 'email' : 'phone');
        }

        return $registration->id;
    }

    public function markClaimed(string $userId, string $email, DateTimeImmutable $claimedAt): void
    {
        try {
            User::query()
                ->whereKey($userId)
                ->whereNull('archived_at')
                ->update([
                    'email' => $email,
                    'email_verified_at' => $claimedAt,
                    'claimed_at' => $claimedAt,
                    'updated_at' => $claimedAt,
                ]);
        } catch (QueryException $e) {
            throw $this->translateDuplicate($e, 'email');
        }
    }

    public function bindPhone(string $userId, string $phoneE164Hash): void
    {
        try {
            User::query()
                ->whereKey($userId)
                ->whereNull('archived_at')
                ->update([
                    'phone_e164_hash' => $phoneE164Hash,
                    'updated_at' => new DateTimeImmutable('now'),
                ]);
        } catch (QueryException $e) {
            throw $this->translateDuplicate($e, 'phone');
        }
    }

    public function bindEmail(string $userId, string $email, DateTimeImmutable $verifiedAt): void
    {
        try {
            User::query()
                ->whereKey($userId)
                ->whereNull('archived_at')
                ->update([
                    'email' => $email,
                    'email_verified_at' => $verifiedAt,
                    'updated_at' => $verifiedAt,
                ]);
        } catch (QueryException $e) {
            throw $this->translateDuplicate($e, 'email');
        }
    }

    public function emailInUse(string $email): bool
    {
        return User::query()
            ->where('email', $email)
            ->whereNull('archived_at')
            ->exists();
    }

    public function phoneInUse(string $phoneE164Hash): bool
    {
        return User::query()
            ->where('phone_e164_hash', $phoneE164Hash)
            ->whereNull('archived_at')
            ->exists();
    }

    private function translateDuplicate(QueryException $e, string $channel): \Throwable
    {
        $sqlState = $e->errorInfo[0] ?? null;

        if ($sqlState === '23505' || $sqlState === '23000') {
            return new DuplicateChannelException($channel);
        }

        return $e;
    }
}
