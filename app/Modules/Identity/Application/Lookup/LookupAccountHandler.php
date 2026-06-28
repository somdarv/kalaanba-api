<?php

declare(strict_types=1);

namespace Kalaanba\Modules\Identity\Application\Lookup;

use Illuminate\Validation\ValidationException;
use Kalaanba\Modules\Identity\Application\Registration\UserRegistrationRepository;
use Kalaanba\Support\Auth\PhoneHash;

/**
 * Use case: resolve whether an identifier (phone E.164 or email) maps to an
 * existing **active** (non-archived) account, so the identifier-first entry
 * screen can branch copy/flow before committing to a channel-specific action.
 *
 * Read-only — never mutates state, never issues an OTP or email. Returns only
 * the existence boolean + inferred channel; never any PII.
 *
 * Refs:
 *  - docs/engines/identity/Identity_Engine_System_Document.md §4, §6, §12
 *  - docs/adr/0004-identifier-first-progressive-auth.md
 */
final readonly class LookupAccountHandler
{
    /** E.164: a leading + then 7–15 digits (matches the OTP/registration rule). */
    private const PHONE_PATTERN = '/^\+[1-9]\d{6,14}$/';

    public function __construct(
        private UserRegistrationRepository $users,
        private PhoneHash $phoneHash,
    ) {}

    public function handle(string $identifier): LookupResult
    {
        $identifier = trim($identifier);

        if (preg_match(self::PHONE_PATTERN, $identifier) === 1) {
            return new LookupResult(
                exists: $this->users->phoneInUse($this->phoneHash->hash($identifier)),
                channel: 'phone',
            );
        }

        // Normalise exactly as registration does (mb_strtolower + trim) so the
        // existence check matches what is actually stored.
        $email = mb_strtolower($identifier);
        if (filter_var($email, FILTER_VALIDATE_EMAIL) !== false) {
            return new LookupResult(
                exists: $this->users->emailInUse($email),
                channel: 'email',
            );
        }

        throw ValidationException::withMessages([
            'identifier' => ['auth.identifier_invalid'],
        ]);
    }
}
