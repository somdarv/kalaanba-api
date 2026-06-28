<?php

declare(strict_types=1);

namespace Kalaanba\Modules\Identity\Application\Registration;

/**
 * Persistence port for user-row writes performed by the Identity engine's
 * registration + channel-binding flows.
 *
 * Adapter lives at `App\Infrastructure\Identity\` because it bridges
 * `App\Models\User` — engine modules are forbidden by architecture test
 * from depending on App\Models directly.
 */
interface UserRegistrationRepository
{
    /**
     * Insert a new user row. Returns the persisted user id.
     *
     * Adapter is responsible for translating unique-violation errors into
     * {@see DuplicateChannelException}.
     */
    public function create(NewUserRegistration $registration): string;

    /**
     * Mark a user as CLAIMED at the given timestamp and stamp their
     * confirmed email. Used by the email-verify confirmation path.
     */
    public function markClaimed(string $userId, string $email, \DateTimeImmutable $claimedAt): void;

    /**
     * Attach a phone channel to an already-CLAIMED user.
     *
     * @throws DuplicateChannelException when another active user already
     *                                   owns this phone hash.
     */
    public function bindPhone(string $userId, string $phoneE164Hash): void;

    /**
     * Attach an email channel to an already-CLAIMED user. Adapter must
     * stamp `email_verified_at` to the supplied timestamp.
     *
     * @throws DuplicateChannelException when another active user already
     *                                   owns this email.
     */
    public function bindEmail(string $userId, string $email, \DateTimeImmutable $verifiedAt): void;

    /**
     * True when an active (non-archived) row exists with this email.
     */
    public function emailInUse(string $email): bool;

    /**
     * True when an active (non-archived) row exists with this phone hash.
     */
    public function phoneInUse(string $phoneE164Hash): bool;
}
