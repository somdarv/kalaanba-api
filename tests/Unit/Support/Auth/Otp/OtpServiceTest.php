<?php

declare(strict_types=1);

use Illuminate\Cache\ArrayStore;
use Illuminate\Cache\Repository as CacheRepository;
use Kalaanba\Support\Auth\Otp\CacheOtpStore;
use Kalaanba\Support\Auth\Otp\CodeGenerator;
use Kalaanba\Support\Auth\Otp\Exceptions\OtpAttemptsExhaustedException;
use Kalaanba\Support\Auth\Otp\Exceptions\OtpExpiredException;
use Kalaanba\Support\Auth\Otp\Exceptions\OtpInvalidException;
use Kalaanba\Support\Auth\Otp\Exceptions\OtpNotFoundException;
use Kalaanba\Support\Auth\Otp\MockOtpProvider;
use Kalaanba\Support\Auth\Otp\OtpService;
use Kalaanba\Support\Auth\PhoneHash;
use Psr\Clock\ClockInterface;

final class FixedClock implements ClockInterface
{
    public function __construct(private DateTimeImmutable $now) {}

    public function now(): DateTimeImmutable
    {
        return $this->now;
    }

    public function advance(string $modifier): void
    {
        $this->now = $this->now->modify($modifier);
    }
}

final class StaticCodeGenerator implements CodeGenerator
{
    public function __construct(private readonly string $code) {}

    public function generate(int $length): string
    {
        return str_pad($this->code, $length, '0', STR_PAD_LEFT);
    }
}

/**
 * @return array{0:OtpService,1:MockOtpProvider,2:CacheOtpStore}
 */
function makeService(
    CodeGenerator $generator,
    ClockInterface $clock,
    int $ttlSeconds = 300,
    int $codeLength = 6,
    int $maxAttempts = 3,
): array {
    $cache = new CacheRepository(new ArrayStore);
    $store = new CacheOtpStore($cache);
    $provider = new MockOtpProvider;
    $phoneHash = new PhoneHash('unit-test-secret');

    $service = new OtpService(
        store: $store,
        provider: $provider,
        phoneHash: $phoneHash,
        clock: $clock,
        codeGenerator: $generator,
        codeSecret: 'unit-test-secret',
        ttlSeconds: $ttlSeconds,
        codeLength: $codeLength,
        maxAttempts: $maxAttempts,
    );

    return [$service, $provider, $store];
}

it('issues a code of the configured length and persists a record', function (): void {
    $clock = new FixedClock(new DateTimeImmutable('2026-05-22T10:00:00Z'));
    [$service, $provider] = makeService(new StaticCodeGenerator('000042'), $clock, ttlSeconds: 60, codeLength: 6);

    $issuance = $service->issue('+233244123456');

    expect($provider->lastSent())->not->toBeNull();
    expect((string) $provider->lastSent()['code'])->toBe('000042');
    expect($issuance->codeLength)->toBe(6);
    expect($issuance->expiresAt->getTimestamp())->toBe($clock->now()->getTimestamp() + 60);
});

it('accepts the correct code exactly once', function (): void {
    $clock = new FixedClock(new DateTimeImmutable('2026-05-22T10:00:00Z'));
    [$service, $provider] = makeService(new StaticCodeGenerator('123456'), $clock);

    $service->issue('+233244123456');
    $code = (string) $provider->lastSent()['code'];

    $service->verify('+233244123456', $code);

    expect(fn () => $service->verify('+233244123456', $code))
        ->toThrow(OtpNotFoundException::class);
});

it('rejects an expired OTP and forgets it', function (): void {
    $clock = new FixedClock(new DateTimeImmutable('2026-05-22T10:00:00Z'));
    [$service, $provider] = makeService(new StaticCodeGenerator('111111'), $clock, ttlSeconds: 60);

    $service->issue('+233244123456');
    $clock->advance('+61 seconds');

    expect(fn () => $service->verify('+233244123456', (string) $provider->lastSent()['code']))
        ->toThrow(OtpExpiredException::class);
});

it('increments attempts on a wrong code and invalidates after the cap', function (): void {
    $clock = new FixedClock(new DateTimeImmutable('2026-05-22T10:00:00Z'));
    [$service] = makeService(new StaticCodeGenerator('111111'), $clock, maxAttempts: 3);

    $service->issue('+233244123456');

    expect(fn () => $service->verify('+233244123456', '999999'))
        ->toThrow(OtpInvalidException::class);
    expect(fn () => $service->verify('+233244123456', '999998'))
        ->toThrow(OtpInvalidException::class);

    expect(fn () => $service->verify('+233244123456', '999997'))
        ->toThrow(OtpAttemptsExhaustedException::class);

    expect(fn () => $service->verify('+233244123456', '999996'))
        ->toThrow(OtpNotFoundException::class);
});

it('throws OtpNotFound when no code has been issued', function (): void {
    [$service] = makeService(
        new StaticCodeGenerator('000000'),
        new FixedClock(new DateTimeImmutable('2026-05-22T10:00:00Z')),
    );

    expect(fn () => $service->verify('+233244123456', '000000'))
        ->toThrow(OtpNotFoundException::class);
});
