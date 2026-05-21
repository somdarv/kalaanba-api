<?php

declare(strict_types=1);

namespace Kalaanba\Support\Auth\Otp;

use Random\Randomizer;

final class RandomCodeGenerator implements CodeGenerator
{
    public function __construct(private readonly Randomizer $randomizer) {}

    public function generate(int $length): string
    {
        $maxExclusive = 10 ** $length;
        $value = $this->randomizer->getInt(0, $maxExclusive - 1);

        return str_pad((string) $value, $length, '0', STR_PAD_LEFT);
    }
}
