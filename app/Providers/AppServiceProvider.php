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
use Kalaanba\Support\Auth\Otp\BmsOtpProvider;
use Kalaanba\Support\Auth\Otp\CacheOtpStore;
use Kalaanba\Support\Auth\Otp\CodeGenerator;
use Kalaanba\Support\Auth\Otp\MockOtpProvider;
use Kalaanba\Support\Auth\Otp\OtpProvider;
use Kalaanba\Support\Auth\Otp\OtpService;
use Kalaanba\Support\Auth\Otp\OtpStore;
use Kalaanba\Support\Auth\Otp\RandomCodeGenerator;
use Kalaanba\Support\Auth\Otp\SmsOnlineGhOtpProvider;
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
        // config key. `smsonlinegh` is the live driver (ADR-0008); `mock` is
        // dev/test only and is refused outright in production, because a mock
        // in production is a silent black hole — the code is generated, stored
        // and delivered nowhere, and every surface reports success.
        $this->app->singleton(MockOtpProvider::class);
        $this->app->bind(OtpProvider::class, function () {
            $providerName = $this->resolveOtpProviderName();

            return match ($providerName) {
                'mock' => $this->makeMockOtpProvider(),
                'bms' => $this->makeBmsOtpProvider(),
                'smsonlinegh' => $this->makeSmsOnlineGhOtpProvider(),
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

        // WP-20260702 — player-profile creation. One-per-user + idempotent;
        // this bounds abusive retries. Keyed by the acting user.
        RateLimiter::for('player-create', function (Request $request): Limit {
            $perMinute = $this->readConfigInt('player.profile.create.throttle.per_minute', 5);
            $userId = (string) ($request->user()?->getAuthIdentifier() ?? '');
            $ipKey = (string) ($request->ip() ?? 'unknown');

            return Limit::perMinute($perMinute)->by($userId !== '' ? 'u:'.$userId : 'ip:'.$ipKey);
        });

        // WP-20260819 — public player-profile vocabulary read (ADR-0007).
        // Reference data, generous per-IP cap like the Zone reads.
        RateLimiter::for('player-read', function (Request $request): Limit {
            $perMinute = $this->readConfigInt('player.throttle.read.per_minute', 60);
            $ipKey = (string) ($request->ip() ?? 'unknown');

            return Limit::perMinute($perMinute)->by('ip:'.$ipKey);
        });

        // WP-20260822 — player photo upload. Keyed by the acting user rather
        // than by IP: a shared connection at an internet cafe or one club's
        // wifi is the ordinary case here, and an IP key would let one player's
        // retries lock out everyone beside them.
        RateLimiter::for('player-media-upload', function (Request $request): Limit {
            $perMinute = $this->readConfigInt('player.media.throttle.per_minute', 10);
            $userId = (string) ($request->user()?->getAuthIdentifier() ?? '');
            $ipKey = (string) ($request->ip() ?? 'unknown');

            return Limit::perMinute($perMinute)->by($userId !== '' ? 'u:'.$userId : 'ip:'.$ipKey);
        });

        // WP-20260702 (WP-C1) — club creation + discovery, keyed by the caller.
        RateLimiter::for('club-create', function (Request $request): Limit {
            $perMinute = $this->readConfigInt('club.create.throttle.per_minute', 5);
            $userId = (string) ($request->user()?->getAuthIdentifier() ?? '');
            $ipKey = (string) ($request->ip() ?? 'unknown');

            return Limit::perMinute($perMinute)->by($userId !== '' ? 'u:'.$userId : 'ip:'.$ipKey);
        });

        RateLimiter::for('club-read', function (Request $request): Limit {
            $perMinute = $this->readConfigInt('club.read.throttle.per_minute', 60);
            $userId = (string) ($request->user()?->getAuthIdentifier() ?? '');
            $ipKey = (string) ($request->ip() ?? 'unknown');

            return Limit::perMinute($perMinute)->by($userId !== '' ? 'u:'.$userId : 'ip:'.$ipKey);
        });

        // WP-20260702 (WP-C2) — affiliation join request + decide, per caller.
        RateLimiter::for('affiliation-join', function (Request $request): Limit {
            $perMinute = $this->readConfigInt('affiliation.join.throttle.per_minute', 10);
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

    /**
     * The mock provider captures codes in memory and prints them only in the
     * `local` environment. In production that is indistinguishable from an
     * outage that nobody is paged for, so refuse it rather than serve it.
     */
    private function makeMockOtpProvider(): OtpProvider
    {
        if ($this->app->environment('production')) {
            throw new \RuntimeException(
                'auth.otp_provider is "mock" in production: OTPs would be generated and '
                .'delivered nowhere. Set it to a live provider (see ADR-0008).',
            );
        }

        return app(MockOtpProvider::class);
    }

    /**
     * BMS (Bulk Messaging Solutions) — the live driver as of ADR-0009.
     *
     * Same env-vs-config split as every other gateway here: the credential is
     * env-only, the sender ID and wording are admin config (Constitution Law 2).
     */
    private function makeBmsOtpProvider(): OtpProvider
    {
        $apiKey = (string) config('bms.api_key', '');

        if ($apiKey === '') {
            throw new \RuntimeException(
                'auth.otp_provider is "bms" but BMS_API_KEY is empty.',
            );
        }

        return new BmsOtpProvider(
            apiKey: $apiKey,
            senderId: $this->readConfigString('auth.otp.sms.sender_id', 'Kalaanba'),
            baseUrl: (string) config('bms.base_url'),
            timeoutSeconds: (int) config('bms.timeout_seconds', 10),
            messageTemplate: $this->readConfigString(
                'auth.otp.sms.message_template',
                'Your Kalaanba code is {code}. It expires in 5 minutes.',
            ),
        );
    }

    /**
     * Built here rather than auto-wired because the credential is env-only
     * while the sender ID and wording are admin config (Constitution Law 2),
     * so the two halves come from different places on purpose.
     */
    private function makeSmsOnlineGhOtpProvider(): OtpProvider
    {
        $apiKey = (string) config('smsonlinegh.api_key', '');

        if ($apiKey === '') {
            // Fail at resolve time with a message that names the fix. Selecting
            // a gateway driver with no credential would otherwise surface as a
            // per-request delivery failure with no hint as to why.
            throw new \RuntimeException(
                'auth.otp_provider is "smsonlinegh" but SMSONLINEGH_API_KEY is empty.',
            );
        }

        return new SmsOnlineGhOtpProvider(
            apiKey: $apiKey,
            // 11 characters is the alphanumeric ceiling, and an UNREGISTERED
            // sender is accepted by the gateway and then never delivered — see
            // the provider docblock. Config, so it is fixable without a deploy.
            senderId: $this->readConfigString('auth.otp.sms.sender_id', 'Kalaanba'),
            baseUrl: (string) config('smsonlinegh.base_url'),
            timeoutSeconds: (int) config('smsonlinegh.timeout_seconds', 10),
            messageTemplate: $this->readConfigString(
                'auth.otp.sms.message_template',
                'Your Kalaanba code is {code}. It expires in 5 minutes.',
            ),
        );
    }

    private function readConfigString(string $key, string $fallback): string
    {
        try {
            $value = KxConfig::get($key);
            if ($value === null) {
                return $fallback;
            }

            $resolved = (string) $value->value;

            return $resolved === '' ? $fallback : $resolved;
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
