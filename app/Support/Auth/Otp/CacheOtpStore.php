<?php

declare(strict_types=1);

namespace Kalaanba\Support\Auth\Otp;

use Illuminate\Contracts\Cache\Repository as CacheRepository;

/**
 * Cache-backed OTP store.
 *
 * Production uses the Redis cache store; tests run against Laravel's
 * `array` driver so OTP storage is deterministic and isolated.
 */
final class CacheOtpStore implements OtpStore
{
    private const string KEY_PREFIX = 'kx:otp:v1:';

    public function __construct(private readonly CacheRepository $cache) {}

    public function put(OtpRecord $record, int $ttlSeconds): void
    {
        $this->cache->put($this->cacheKey($record->phoneHash), $record->toArray(), $ttlSeconds);
    }

    public function find(string $phoneHash): ?OtpRecord
    {
        $payload = $this->cache->get($this->cacheKey($phoneHash));

        if (! is_array($payload)) {
            return null;
        }

        /** @var array{phone_hash:string,code_hash:string,attempts:int,issued_at:int,expires_at:int} $payload */
        return OtpRecord::fromArray($payload);
    }

    public function forget(string $phoneHash): void
    {
        $this->cache->forget($this->cacheKey($phoneHash));
    }

    private function cacheKey(string $phoneHash): string
    {
        return self::KEY_PREFIX.$phoneHash;
    }
}
