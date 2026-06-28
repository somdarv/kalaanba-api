<?php

declare(strict_types=1);

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Kalaanba\Support\Audit\AdminAuditWriter;
use Kalaanba\Support\Audit\DatabaseAdminAuditWriter;
use Kalaanba\Support\Audit\PayloadRedactor;
use Kalaanba\Support\Auth\Otp\CacheOtpStore;
use Kalaanba\Support\Auth\Otp\CodeGenerator;
use Kalaanba\Support\Auth\Otp\MockOtpProvider;
use Kalaanba\Support\Auth\Otp\OtpProvider;
use Kalaanba\Support\Auth\Otp\OtpService;
use Kalaanba\Support\Auth\Otp\OtpStore;
use Kalaanba\Support\Auth\Otp\RandomCodeGenerator;
use Kalaanba\Support\Auth\PhoneHash;
use Kalaanba\Support\Auth\Scope\DenyAllScopeResolver;
use Kalaanba\Support\Auth\Scope\ScopeResolver;
use Kalaanba\Support\Config as KxConfig;
use Psr\Clock\ClockInterface;
use Random\Randomizer;
use Symfony\Component\Clock\NativeClock;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(ClockInterface::class, NativeClock::class);
        $this->app->singleton(Randomizer::class, fn (): Randomizer => new Randomizer);
        $this->app->singleton(
            CodeGenerator::class,
            fn (): CodeGenerator => new RandomCodeGenerator(app(Randomizer::class)),
        );

        $this->app->singleton(PhoneHash::class, fn (): PhoneHash => new PhoneHash(
            (string) config('app.key'),
        ));

        $this->app->singleton(OtpStore::class, fn (): OtpStore => new CacheOtpStore(
            app(CacheRepository::class),
        ));

        // OTP provider is selected at runtime by the `auth.otp_provider`
        // config key. Today the only registered implementation is `mock`;
        // WhatsApp lands in Build Plan Phase 4.
        $this->app->singleton(MockOtpProvider::class);
        $this->app->bind(OtpProvider::class, function () {
            $providerName = $this->resolveOtpProviderName();

            return match ($providerName) {
                'mock' => app(MockOtpProvider::class),
                default => throw new \RuntimeException(
                    sprintf('Unsupported auth.otp_provider value: %s', $providerName),
                ),
            };
        });

        $this->app->bind(OtpService::class, fn (): OtpService => new OtpService(
            store: app(OtpStore::class),
            provider: app(OtpProvider::class),
            phoneHash: app(PhoneHash::class),
            clock: app(ClockInterface::class),
            codeGenerator: app(CodeGenerator::class),
            codeSecret: (string) config('app.key'),
            ttlSeconds: $this->readConfigInt('auth.otp_ttl_seconds', 300),
            codeLength: $this->readConfigInt('auth.otp_length', 6),
            maxAttempts: $this->readConfigInt('auth.otp_max_attempts', 5),
        ));

        $this->app->bind(ScopeResolver::class, DenyAllScopeResolver::class);

        $this->app->singleton(PayloadRedactor::class);
        $this->app->singleton(
            AdminAuditWriter::class,
            fn (): AdminAuditWriter => new DatabaseAdminAuditWriter(
                connection: $this->app['db']->connection(),
            ),
        );
    }

    public function boot(): void
    {
        $isNotProduction = ! $this->app->isProduction();

        Model::preventLazyLoading($isNotProduction);
        Model::preventAccessingMissingAttributes($isNotProduction);
        Model::preventSilentlyDiscardingAttributes($isNotProduction);

        $this->configureRateLimiters();
    }

    /**
     * Auth + OTP rate limits — engineering-standards §11 (strict on
     * credential-bearing endpoints).
     */
    private function configureRateLimiters(): void
    {
        RateLimiter::for('auth', static function (Request $request): Limit {
            $email = (string) $request->input('email', '');
            $ipKey = (string) ($request->ip() ?? 'unknown');

            return Limit::perMinute(5)->by($email !== '' ? $email.'|'.$ipKey : $ipKey);
        });

        RateLimiter::for('otp', static function (Request $request): Limit {
            $phone = (string) $request->input('phone_e164', '');
            $ipKey = (string) ($request->ip() ?? 'unknown');

            return Limit::perMinute(5)->by($phone !== '' ? 'p:'.$phone.'|'.$ipKey : 'ip:'.$ipKey);
        });

        // WP-20260624 — identifier-first account lookup (ADR-0004 §3).
        // Strict, keyed by BOTH identifier and IP to bound enumeration.
        RateLimiter::for('lookup', function (Request $request): Limit {
            $perMinute = $this->readConfigInt('auth.throttle.lookup.per_minute', 5);
            $identifier = (string) $request->input('identifier', '');
            $ipKey = (string) ($request->ip() ?? 'unknown');

            return Limit::perMinute($perMinute)
                ->by($identifier !== '' ? 'id:'.sha1($identifier).'|'.$ipKey : 'ip:'.$ipKey);
        });

        // Identity engine — avatar upload + public profile lookup.
        // Tunable from admin config (engineering-standards §10).
        RateLimiter::for('identity-avatar-upload', function (Request $request): Limit {
            $perMinute = $this->readConfigInt('users.avatar.throttle.per_minute', 10);
            $userId = (string) ($request->user()?->getAuthIdentifier() ?? '');
            $ipKey = (string) ($request->ip() ?? 'unknown');

            return Limit::perMinute($perMinute)->by($userId !== '' ? 'u:'.$userId : 'ip:'.$ipKey);
        });

        RateLimiter::for('identity-public-profile', function (Request $request): Limit {
            $perMinute = $this->readConfigInt('users.public_profile.throttle.anonymous_per_minute', 60);
            $userId = (string) ($request->user()?->getAuthIdentifier() ?? '');
            $ipKey = (string) ($request->ip() ?? 'unknown');

            return Limit::perMinute($perMinute)->by($userId !== '' ? 'u:'.$userId : 'ip:'.$ipKey);
        });

        // WP-20260530 — registration / email-verify / channel-bind.
        RateLimiter::for('registration', function (Request $request): Limit {
            $perMinute = $this->readConfigInt('auth.throttle.registration.per_minute', 3);
            $ipKey = (string) ($request->ip() ?? 'unknown');

            return Limit::perMinute($perMinute)->by('ip:'.$ipKey);
        });

        RateLimiter::for('email-verify', function (Request $request): Limit {
            $perMinute = $this->readConfigInt('auth.throttle.email_verify.per_minute', 10);
            $ipKey = (string) ($request->ip() ?? 'unknown');

            return Limit::perMinute($perMinute)->by('ip:'.$ipKey);
        });

        RateLimiter::for('channel-bind', function (Request $request): Limit {
            $perMinute = $this->readConfigInt('auth.throttle.channel_bind.per_minute', 5);
            $userId = (string) ($request->user()?->getAuthIdentifier() ?? '');
            $ipKey = (string) ($request->ip() ?? 'unknown');

            return Limit::perMinute($perMinute)->by($userId !== '' ? 'u:'.$userId : 'ip:'.$ipKey);
        });

        // Public Zone geography reads — reference data, generous per-IP cap.
        RateLimiter::for('zone-read', function (Request $request): Limit {
            $perMinute = $this->readConfigInt('zone.throttle.read.per_minute', 60);
            $ipKey = (string) ($request->ip() ?? 'unknown');

            return Limit::perMinute($perMinute)->by('ip:'.$ipKey);
        });

        // User-facing area suggestion — stricter, keyed by submitter.
        RateLimiter::for('zone-suggest', function (Request $request): Limit {
            $perMinute = $this->readConfigInt('zone.throttle.suggest.per_minute', 5);
            $userId = (string) ($request->user()?->getAuthIdentifier() ?? '');
            $ipKey = (string) ($request->ip() ?? 'unknown');

            return Limit::perMinute($perMinute)->by($userId !== '' ? 'u:'.$userId : 'ip:'.$ipKey);
        });
    }

    private function readConfigInt(string $key, int $fallback): int
    {
        try {
            $value = KxConfig::get($key);
            if ($value === null) {
                return $fallback;
            }

            return (int) $value->value;
        } catch (\Throwable) {
            return $fallback;
        }
    }

    private function resolveOtpProviderName(): string
    {
        try {
            $value = KxConfig::get('auth.otp_provider');

            return $value === null ? 'mock' : (string) $value->value;
        } catch (\Throwable) {
            return 'mock';
        }
    }
}
