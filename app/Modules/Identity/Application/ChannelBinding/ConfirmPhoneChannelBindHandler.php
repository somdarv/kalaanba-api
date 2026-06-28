<?php

declare(strict_types=1);

namespace Kalaanba\Modules\Identity\Application\ChannelBinding;

use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Kalaanba\Modules\Identity\Application\Registration\DuplicateChannelException;
use Kalaanba\Modules\Identity\Application\Registration\UserRegistrationRepository;
use Kalaanba\Support\Auth\Otp\Exceptions\OtpException;
use Kalaanba\Support\Auth\Otp\OtpService;
use Kalaanba\Support\Auth\PhoneHash;
use Kalaanba\Support\EventBus\OutboxEnvelope;
use Kalaanba\Support\EventBus\OutboxWriter;
use Psr\Clock\ClockInterface;

/**
 * Confirm a phone-channel bind by consuming the OTP issued in
 * {@see StartPhoneChannelBindHandler::handle()}.
 *
 * Emits `identity.user_channel_bound` v1. Duplicate-channel re-check
 * runs in-transaction so we don't race with another bind for the same
 * phone.
 */
final readonly class ConfirmPhoneChannelBindHandler
{
    public function __construct(
        private UserRegistrationRepository $users,
        private OtpService $otpService,
        private PhoneHash $phoneHash,
        private OutboxWriter $outbox,
        private ClockInterface $clock,
    ) {}

    public function handle(string $userId, string $phoneE164, string $otp): void
    {
        $hash = $this->phoneHash->hash($phoneE164);

        try {
            $this->otpService->verify($phoneE164, $otp);
        } catch (OtpException $e) {
            throw ValidationException::withMessages([
                'otp' => [$e->errorCode()],
            ]);
        }

        $now = $this->now();

        DB::transaction(function () use ($userId, $hash, $now): void {
            if ($this->users->phoneInUse($hash)) {
                throw new DuplicateChannelException('phone');
            }

            $this->users->bindPhone($userId, $hash);

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
                    'channel' => 'phone',
                    'bound_at' => $now->format(DATE_ATOM),
                ],
            ));
        });
    }

    private function now(): DateTimeImmutable
    {
        return DateTimeImmutable::createFromInterface($this->clock->now())
            ->setTimezone(new DateTimeZone('UTC'));
    }
}
