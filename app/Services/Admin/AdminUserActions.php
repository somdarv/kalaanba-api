<?php

declare(strict_types=1);

namespace App\Services\Admin;

use App\Models\User;
use Illuminate\Support\Carbon;
use Kalaanba\Support\Auth\Otp\OtpService;
use Kalaanba\Support\Auth\Otp\OtpStore;
use Kalaanba\Support\Auth\PhoneHash;

/**
 * Write-side of the admin Users section — the pre-alpha support toolbox.
 *
 * Every method mutates one user and returns nothing; the controller re-reads
 * via {@see AdminUserDirectory} for the response. No secret is ever read: a
 * new password is hashed on save (never the old one), OTPs are re-issued
 * (never displayed). Audit is automatic via AdminAuditMiddleware.
 *
 * Refs: Identity doc §4/§12; ADR-0005; WP-20260624.
 */
final class AdminUserActions
{
    public function __construct(
        private readonly PhoneHash $phoneHash,
        private readonly OtpService $otpService,
        private readonly OtpStore $otpStore,
    ) {}

    /** Set a brand-new password. Hashed on save (cast); old value never read. */
    public function setPassword(User $user, string $newPassword): void
    {
        $user->forceFill(['password' => $newPassword])->save();
    }

    /** Admin override: mark a channel verified, transitioning to CLAIMED. */
    public function forceVerify(User $user, string $channel): void
    {
        $now = Carbon::now('UTC');

        if ($channel === 'email') {
            if ($user->email === null) {
                throw new AdminUserActionException('admin.users.no_email', 'User has no email to verify.');
            }
            $user->forceFill([
                'email_verified_at' => $now,
                'claimed_at' => $user->claimed_at ?? $now,
            ])->save();

            return;
        }

        if ($channel === 'phone') {
            if ($user->phone_e164_hash === null) {
                throw new AdminUserActionException('admin.users.no_phone', 'User has no phone to verify.');
            }
            $user->forceFill(['claimed_at' => $user->claimed_at ?? $now])->save();

            return;
        }

        throw new AdminUserActionException('admin.users.invalid_channel', 'Channel must be phone or email.');
    }

    /** Replace the phone number (admin enters the new E.164). */
    public function updatePhone(User $user, string $phoneE164): void
    {
        $user->forceFill([
            'phone_e164_hash' => $this->phoneHash->hash($phoneE164),
            'phone_e164_last4' => substr($phoneE164, -4),
        ])->save();
    }

    /** Replace the email and reset its verified flag (re-verify required). */
    public function updateEmail(User $user, string $email): void
    {
        $user->forceFill([
            'email' => mb_strtolower(trim($email)),
            'email_verified_at' => null,
        ])->save();
    }

    public function disable(User $user): void
    {
        $user->forceFill(['disabled_at' => Carbon::now('UTC')])->save();
    }

    /**
     * Archive (soft-delete) a user — Constitution Law 13 / Identity §6. The row
     * is preserved for history; the account becomes read-only, cannot log in,
     * and its phone/email uniqueness is released so the identifier can be
     * re-registered. There is no hard delete. Admin accounts are protected.
     */
    public function archive(User $user): void
    {
        if ($user->role->isSuperAdmin()) {
            throw new AdminUserActionException(
                'admin.users.cannot_archive_admin',
                'Admin accounts cannot be archived from here.',
            );
        }

        $user->forceFill(['archived_at' => Carbon::now('UTC')])->save();
    }

    public function enable(User $user): void
    {
        $user->forceFill(['disabled_at' => null])->save();
    }

    /**
     * Re-issue an OTP. The admin supplies the number (we never store plaintext,
     * §12); we confirm it matches the user's stored hash before sending.
     */
    public function resendOtp(User $user, string $phoneE164): void
    {
        if (
            $user->phone_e164_hash === null
            || ! hash_equals($user->phone_e164_hash, $this->phoneHash->hash($phoneE164))
        ) {
            throw new AdminUserActionException(
                'admin.users.phone_mismatch',
                'That number does not match this account.',
            );
        }

        $this->otpService->issue($phoneE164);
    }

    /** Clear an OTP lockout (attempt counter / pending code) for the user. */
    public function clearLockout(User $user): void
    {
        if ($user->phone_e164_hash === null) {
            throw new AdminUserActionException('admin.users.no_phone', 'User has no phone lockout to clear.');
        }

        $this->otpStore->forget($user->phone_e164_hash);
    }
}
