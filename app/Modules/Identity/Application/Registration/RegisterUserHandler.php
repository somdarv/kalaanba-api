<?php

declare(strict_types=1);

namespace Kalaanba\Modules\Identity\Application\Registration;

use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Kalaanba\Modules\Identity\Application\EmailVerification\EmailVerificationRepository;
use Kalaanba\Modules\Identity\Domain\EmailVerification\EmailVerificationPurpose;
use Kalaanba\Modules\Identity\Domain\EmailVerification\EmailVerificationToken;
use Kalaanba\Modules\Identity\Domain\Registration\PasswordPolicy;
use Kalaanba\Modules\Identity\Domain\Registration\RegistrationChannel;
use Kalaanba\Modules\Zone\Domain\GeographyReader;
use Kalaanba\Support\Auth\Otp\Exceptions\OtpException;
use Kalaanba\Support\Auth\Otp\OtpService;
use Kalaanba\Support\Auth\PhoneHash;
use Kalaanba\Support\EventBus\OutboxEnvelope;
use Kalaanba\Support\EventBus\OutboxWriter;
use Psr\Clock\ClockInterface;

/**
 * Use case: register a new user (phone-OTP path or email+password path).
 *
 * Cross-engine wiring:
 *  - Zone\GeographyReader  → area existence check (sync read-port).
 *  - Support\OtpService    → consume the OTP proving phone possession.
 *  - Outbox                → emit `identity.user_registered` and, on the
 *                            phone path, `identity.user_claimed`. Email
 *                            path's `user_claimed` is emitted later by
 *                            {@see ConfirmEmailHandler}.
 *
 * Refs:
 *  - docs/engines/identity/Identity_Engine_System_Document.md §7.1
 *  - Constitution §1.1 (no cross-schema joins), §1.6 (event-first),
 *    §1.14 (idempotent — user uuid is the natural key).
 */
final readonly class RegisterUserHandler
{
    public function __construct(
        private GeographyReader $geography,
        private UserRegistrationRepository $users,
        private EmailVerificationRepository $verifications,
        private OtpService $otpService,
        private PhoneHash $phoneHash,
        private OutboxWriter $outbox,
        private ClockInterface $clock,
        private PasswordPolicy $passwordPolicy,
        private bool $registrationEnabled,
        private int $emailVerifyTtlHours,
        private string $defaultRole,
        private bool $exposePlaintextToken,
    ) {}

    public function handle(RegisterUserCommand $command): RegisterUserResult
    {
        if (! $this->registrationEnabled) {
            throw ValidationException::withMessages([
                'channel' => ['auth.registration_closed'],
            ]);
        }

        if ($command->registeredVia !== 'self') {
            throw ValidationException::withMessages([
                'registered_via' => ['auth.registered_via_unsupported'],
            ]);
        }

        // Area is optional at self-signup — users pick it later on the profile
        // screen (no public area picker exists yet). When supplied it must
        // resolve to a real Zone area.
        if ($command->areaId !== null && $this->geography->findAreaById($command->areaId) === null) {
            throw ValidationException::withMessages([
                'area_id' => ['profile.area_not_found'],
            ]);
        }

        $channel = match ($command->channel) {
            'phone' => RegistrationChannel::Phone,
            'email' => RegistrationChannel::Email,
            default => throw ValidationException::withMessages([
                'channel' => ['auth.channel_missing'],
            ]),
        };

        return $channel === RegistrationChannel::Phone
            ? $this->handlePhone($command)
            : $this->handleEmail($command);
    }

    private function handlePhone(RegisterUserCommand $command): RegisterUserResult
    {
        if ($command->phoneE164 === null || $command->otp === null) {
            throw ValidationException::withMessages([
                'phone_e164' => ['auth.channel_missing'],
            ]);
        }

        $phoneHash = $this->phoneHash->hash($command->phoneE164);
        if ($this->users->phoneInUse($phoneHash)) {
            throw new DuplicateChannelException('phone');
        }

        try {
            $this->otpService->verify($command->phoneE164, $command->otp);
        } catch (OtpException $e) {
            throw ValidationException::withMessages([
                'otp' => [$e->errorCode()],
            ]);
        }

        $now = $this->now();
        $userId = (string) Str::uuid();

        DB::transaction(function () use ($userId, $command, $phoneHash, $now): void {
            $this->users->create(new NewUserRegistration(
                id: $userId,
                name: $command->name,
                areaId: $command->areaId,
                email: null,
                phoneE164Hash: $phoneHash,
                passwordHash: null,
                role: $this->defaultRole,
                claimedAt: $now,
                createdAt: $now,
            ));

            $this->outbox->write($this->envelope(
                eventName: 'identity.user_registered',
                userId: $userId,
                occurredAt: $now,
                payload: [
                    'user_id' => $userId,
                    'registered_via' => 'self',
                    'registered_channel' => 'phone',
                    'area_id' => $command->areaId,
                    'name' => $command->name,
                    'registered_at' => $now->format(DATE_ATOM),
                ],
            ));

            // Phone signup transitions directly to CLAIMED — engine doc §7.1.
            $this->outbox->write($this->envelope(
                eventName: 'identity.user_claimed',
                userId: $userId,
                occurredAt: $now,
                payload: [
                    'user_id' => $userId,
                    'claimed_via' => 'self',
                    'claimed_channel' => 'phone',
                    'claimed_at' => $now->format(DATE_ATOM),
                ],
            ));
        });

        return RegisterUserResult::phoneClaimed($userId, $now);
    }

    private function handleEmail(RegisterUserCommand $command): RegisterUserResult
    {
        if ($command->email === null || $command->password === null) {
            throw ValidationException::withMessages([
                'email' => ['auth.channel_missing'],
            ]);
        }

        $email = mb_strtolower(trim($command->email));
        if ($this->users->emailInUse($email)) {
            throw new DuplicateChannelException('email');
        }

        $violations = $this->passwordPolicy->evaluate($command->password);
        if ($violations !== []) {
            throw ValidationException::withMessages([
                'password' => $violations,
            ]);
        }

        $now = $this->now();
        $userId = (string) Str::uuid();
        $plaintext = bin2hex(random_bytes(32));
        $tokenHash = hash('sha256', $plaintext);
        $expiresAt = $now->modify(sprintf('+%d hours', $this->emailVerifyTtlHours));

        $token = new EmailVerificationToken(
            id: (string) Str::uuid(),
            userId: $userId,
            email: $email,
            purpose: EmailVerificationPurpose::Registration,
            tokenHash: $tokenHash,
            expiresAt: $expiresAt,
            consumedAt: null,
            plaintext: $plaintext,
        );

        DB::transaction(function () use ($userId, $command, $email, $token, $now): void {
            $this->users->create(new NewUserRegistration(
                id: $userId,
                name: $command->name,
                areaId: $command->areaId,
                email: $email,
                phoneE164Hash: null,
                passwordHash: Hash::make((string) $command->password),
                role: $this->defaultRole,
                claimedAt: null,
                createdAt: $now,
            ));

            $this->verifications->issue($token);

            $this->outbox->write($this->envelope(
                eventName: 'identity.user_registered',
                userId: $userId,
                occurredAt: $now,
                payload: [
                    'user_id' => $userId,
                    'registered_via' => 'self',
                    'registered_channel' => 'email',
                    'area_id' => $command->areaId,
                    'name' => $command->name,
                    'registered_at' => $now->format(DATE_ATOM),
                ],
            ));
        });

        return RegisterUserResult::emailPendingVerification(
            userId: $userId,
            expiresAt: $expiresAt,
            verificationToken: $this->exposePlaintextToken ? $plaintext : null,
        );
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function envelope(
        string $eventName,
        string $userId,
        DateTimeImmutable $occurredAt,
        array $payload,
    ): OutboxEnvelope {
        return new OutboxEnvelope(
            eventId: (string) Str::uuid(),
            eventName: $eventName,
            schemaVersion: 1,
            occurredAt: $occurredAt,
            actorId: $userId,
            actorRole: 'user',
            source: 'identity',
            payload: $payload,
        );
    }

    private function now(): DateTimeImmutable
    {
        return DateTimeImmutable::createFromInterface($this->clock->now())
            ->setTimezone(new DateTimeZone('UTC'));
    }
}
