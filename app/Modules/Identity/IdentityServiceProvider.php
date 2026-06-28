<?php

declare(strict_types=1);

namespace Kalaanba\Modules\Identity;

use App\Http\Controllers\Identity\ChannelBindingController;
use App\Infrastructure\Identity\EloquentEmailVerificationRepository;
use App\Infrastructure\Identity\EloquentUserProfileRepository;
use App\Infrastructure\Identity\EloquentUserRegistrationRepository;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\ServiceProvider;
use Kalaanba\Modules\Identity\Application\AvatarDriver;
use Kalaanba\Modules\Identity\Application\ChannelBinding\ConfirmPhoneChannelBindHandler;
use Kalaanba\Modules\Identity\Application\ChannelBinding\StartEmailChannelBindHandler;
use Kalaanba\Modules\Identity\Application\ChannelBinding\StartPhoneChannelBindHandler;
use Kalaanba\Modules\Identity\Application\EmailVerification\EmailVerificationRepository;
use Kalaanba\Modules\Identity\Application\Registration\RegisterUserHandler;
use Kalaanba\Modules\Identity\Application\Registration\UserRegistrationRepository;
use Kalaanba\Modules\Identity\Application\UserProfileRepository;
use Kalaanba\Modules\Identity\Domain\Registration\PasswordPolicy;
use Kalaanba\Modules\Identity\Infrastructure\Avatar\AvatarDriverFactory;
use Kalaanba\Modules\Zone\Domain\GeographyReader;
use Kalaanba\Support\Auth\Otp\OtpService;
use Kalaanba\Support\Auth\PhoneHash;
use Kalaanba\Support\Config as KxConfig;
use Kalaanba\Support\EventBus\OutboxWriter;
use Psr\Clock\ClockInterface;

/**
 * Service provider for the Identity engine module.
 *
 * Engine doc (canonical): docs/engines/identity/Identity_Engine_System_Document.md
 * Engine boundary rules:  docs/engine-boundaries.md
 * Layering rules:         app/Modules/README.md
 *
 * MUST NOT:
 *  - Reach into another module's namespace directly.
 *  - Bypass the outbox for cross-engine effects.
 */
final class IdentityServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(
            AvatarDriver::class,
            static fn ($app): AvatarDriver => $app->make(AvatarDriverFactory::class)->make(),
        );

        $this->app->bind(UserProfileRepository::class, EloquentUserProfileRepository::class);
        $this->app->bind(UserRegistrationRepository::class, EloquentUserRegistrationRepository::class);
        $this->app->bind(EmailVerificationRepository::class, EloquentEmailVerificationRepository::class);

        $this->app->bind(PasswordPolicy::class, fn (): PasswordPolicy => new PasswordPolicy(
            minLength: $this->configInt('auth.password.min_length', 10),
            requireMixedCase: $this->configBool('auth.password.require_mixed_case', true),
            requireNumber: $this->configBool('auth.password.require_number', true),
            requireSymbol: $this->configBool('auth.password.require_symbol', false),
        ));

        $this->app->bind(RegisterUserHandler::class, fn (Application $app): RegisterUserHandler => new RegisterUserHandler(
            geography: $app->make(GeographyReader::class),
            users: $app->make(UserRegistrationRepository::class),
            verifications: $app->make(EmailVerificationRepository::class),
            otpService: $app->make(OtpService::class),
            phoneHash: $app->make(PhoneHash::class),
            outbox: $app->make(OutboxWriter::class),
            clock: $app->make(ClockInterface::class),
            passwordPolicy: $app->make(PasswordPolicy::class),
            registrationEnabled: $this->configBool('auth.registration_enabled', true),
            emailVerifyTtlHours: $this->configInt('auth.email_verify.ttl_hours', 24),
            defaultRole: $this->configString('auth.registration_default_role', 'user'),
            exposePlaintextToken: $this->configBool('auth.expose_email_verify_token', false),
        ));

        $this->app->bind(StartEmailChannelBindHandler::class, fn (Application $app): StartEmailChannelBindHandler => new StartEmailChannelBindHandler(
            users: $app->make(UserRegistrationRepository::class),
            verifications: $app->make(EmailVerificationRepository::class),
            clock: $app->make(ClockInterface::class),
            emailVerifyTtlHours: $this->configInt('auth.email_verify.ttl_hours', 24),
        ));

        // ChannelBindingController takes a primitive bool; bind explicitly.
        $this->app->bind(ChannelBindingController::class, fn (Application $app): ChannelBindingController => new ChannelBindingController(
            startPhone: $app->make(StartPhoneChannelBindHandler::class),
            confirmPhone: $app->make(ConfirmPhoneChannelBindHandler::class),
            startEmail: $app->make(StartEmailChannelBindHandler::class),
            exposeEmailToken: $this->configBool('auth.expose_email_verify_token', false),
        ));
    }

    public function boot(): void
    {
        // Module-scoped routes, listeners, etc. registered here as the
        // engine grows.
    }

    private function configInt(string $key, int $fallback): int
    {
        try {
            $value = KxConfig::get($key);

            return $value === null ? $fallback : (int) $value->value;
        } catch (\Throwable) {
            return $fallback;
        }
    }

    private function configBool(string $key, bool $fallback): bool
    {
        try {
            $value = KxConfig::get($key);
            if ($value === null) {
                return $fallback;
            }
            $raw = $value->value;
            if (is_bool($raw)) {
                return $raw;
            }
            $str = strtolower((string) $raw);

            return in_array($str, ['1', 'true', 'yes', 'on'], true);
        } catch (\Throwable) {
            return $fallback;
        }
    }

    private function configString(string $key, string $fallback): string
    {
        try {
            $value = KxConfig::get($key);

            return $value === null ? $fallback : (string) $value->value;
        } catch (\Throwable) {
            return $fallback;
        }
    }
}
