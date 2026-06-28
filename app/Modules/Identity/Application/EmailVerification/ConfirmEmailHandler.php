<?php

declare(strict_types=1);

namespace Kalaanba\Modules\Identity\Application\EmailVerification;

use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Kalaanba\Modules\Identity\Application\Registration\DuplicateChannelException;
use Kalaanba\Modules\Identity\Application\Registration\UserRegistrationRepository;
use Kalaanba\Modules\Identity\Domain\EmailVerification\EmailVerificationPurpose;
use Kalaanba\Support\EventBus\OutboxEnvelope;
use Kalaanba\Support\EventBus\OutboxWriter;
use Psr\Clock\ClockInterface;

/**
 * Use case: consume an email verification token.
 *
 * Dispatches by purpose:
 *  - Registration → mark user CLAIMED + stamp email_verified_at + emit
 *                   `identity.user_claimed`.
 *  - BindEmail    → bind email to an already-CLAIMED user + emit
 *                   `identity.user_channel_bound`.
 *
 * Error codes (all 422 except duplicate-channel which is 409):
 *  - auth.email_verify.token_unknown
 *  - auth.email_verify.token_expired
 *  - auth.email_verify.token_consumed
 *  - identity.channel.email_already_bound (DuplicateChannelException → 409)
 */
final readonly class ConfirmEmailHandler
{
    public function __construct(
        private EmailVerificationRepository $verifications,
        private UserRegistrationRepository $users,
        private OutboxWriter $outbox,
        private ClockInterface $clock,
    ) {}

    public function handle(ConfirmEmailCommand $command): ConfirmEmailResult
    {
        $token = $this->verifications->findByPlaintext($command->plaintextToken);

        if ($token === null) {
            throw ValidationException::withMessages([
                'token' => ['auth.email_verify.token_unknown'],
            ]);
        }

        $now = $this->now();

        if ($token->isConsumed()) {
            throw ValidationException::withMessages([
                'token' => ['auth.email_verify.token_consumed'],
            ]);
        }

        if ($token->isExpired($now)) {
            throw ValidationException::withMessages([
                'token' => ['auth.email_verify.token_expired'],
            ]);
        }

        return DB::transaction(function () use ($token, $now): ConfirmEmailResult {
            $this->verifications->consume($token->id, $now);

            return match ($token->purpose) {
                EmailVerificationPurpose::Registration => $this->claimRegistration($token->userId, $token->email, $now),
                EmailVerificationPurpose::BindEmail => $this->bindEmail($token->userId, $token->email, $now),
            };
        });
    }

    private function claimRegistration(string $userId, string $email, DateTimeImmutable $now): ConfirmEmailResult
    {
        $this->users->markClaimed($userId, $email, $now);

        $this->outbox->write(new OutboxEnvelope(
            eventId: (string) Str::uuid(),
            eventName: 'identity.user_claimed',
            schemaVersion: 1,
            occurredAt: $now,
            actorId: $userId,
            actorRole: 'user',
            source: 'identity',
            payload: [
                'user_id' => $userId,
                'claimed_via' => 'self',
                'claimed_channel' => 'email',
                'claimed_at' => $now->format(DATE_ATOM),
            ],
        ));

        return new ConfirmEmailResult($userId, $email, EmailVerificationPurpose::Registration);
    }

    private function bindEmail(string $userId, string $email, DateTimeImmutable $now): ConfirmEmailResult
    {
        if ($this->users->emailInUse($email)) {
            throw new DuplicateChannelException('email');
        }

        $this->users->bindEmail($userId, $email, $now);

        $this->outbox->write(new OutboxEnvelope(
            eventId: (string) Str::uuid(),
            eventName: 'identity.user_channel_bound',
            schemaVersion: 1,
            occurredAt: $now,
            actorId: $userId,
            actorRole: 'user',
            source: 'identity',
            payload: [
                'user_id' => $userId,
                'channel' => 'email',
                'bound_at' => $now->format(DATE_ATOM),
            ],
        ));

        return new ConfirmEmailResult($userId, $email, EmailVerificationPurpose::BindEmail);
    }

    private function now(): DateTimeImmutable
    {
        return DateTimeImmutable::createFromInterface($this->clock->now())
            ->setTimezone(new DateTimeZone('UTC'));
    }
}
