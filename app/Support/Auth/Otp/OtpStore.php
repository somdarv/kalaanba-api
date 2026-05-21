<?php

declare(strict_types=1);

namespace Kalaanba\Support\Auth\Otp;

interface OtpStore
{
    public function put(OtpRecord $record, int $ttlSeconds): void;

    public function find(string $phoneHash): ?OtpRecord;

    public function forget(string $phoneHash): void;
}
