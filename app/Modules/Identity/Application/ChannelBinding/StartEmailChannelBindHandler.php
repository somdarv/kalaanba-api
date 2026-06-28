<?php

declare(strict_types=1);

namespace Kalaanba\Modules\Identity\Application\ChannelBinding;

use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Kalaanba\Modules\Identity\Application\EmailVerification\ConfirmEmailHandler;
use Kalaanba\Modules\Identity\Application\EmailVerification\EmailVerificationRepository;
use Kalaanba\Modules\Identity\Application\Registration\DuplicateChannelException;
use Kalaanba\Modules\Identity\Application\Registration\UserRegistrationRepository;
use Kalaanba\Modules\Identity\Domain\EmailVerification\EmailVerificationPurpose;
use Kalaanba\Modules\Identity\Domain\EmailVerification\EmailVerificationToken;
use Psr\Clock\ClockInterface;

/**
 * Start binding an email channel to an already-authenticated user.
 *
 * Issues an EmailVerificationToken with purpose `bind_email`. Confirmation
 * is handled by the existing {@see ConfirmEmailHandler}
 * which dispatches on the purpose enum.
 *
 * Returns the issued token's metadata. The plaintext is included on the
 * return value so the Http boundary can hand it to Notification (or, on
 * the log-driver, surface it for tests).
 */
final readonly class StartEmailChannelBindHandler
{
    public function __construct(
        private UserRegistrationRepository $users,
        private EmailVerificationRepository $verifications,
        private ClockInterface $clock,
        private int $emailVerifyTtlHours,
    ) {}

    public function handle(string $userId, string $email): EmailVerificationToken
    {
        $normalised = mb_strtolower(trim($email));

        if ($this->users->emailInUse($normalised)) {
            throw new DuplicateChannelException('email');
        }

        $now = DateTimeImmutable::createFromInterface($this->clock->now())
            ->setTimezone(new DateTimeZone('UTC'));
        $plaintext = bin2hex(random_bytes(32));
        $tokenHash = hash('sha256', $plaintext);
        $expiresAt = $now->modify(sprintf('+%d hours', $this->emailVerifyTtlHours));

        $token = new EmailVerificationToken(
            id: (string) Str::uuid(),
            userId: $userId,
            email: $normalised,
            purpose: EmailVerificationPurpose::BindEmail,
            tokenHash: $tokenHash,
            expiresAt: $expiresAt,
            consumedAt: null,
            plaintext: $plaintext,
        );

        DB::transaction(fn () => $this->verifications->issue($token));

        return $token;
    }
}
