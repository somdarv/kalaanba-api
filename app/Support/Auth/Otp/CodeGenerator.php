<?php

declare(strict_types=1);

namespace Kalaanba\Support\Auth\Otp;

/**
 * Generates a numeric OTP of the requested length.
 *
 * Default implementation wraps `Random\Randomizer` (CSPRNG). Tests bind a
 * deterministic implementation. The Random\Randomizer class itself is
 * `final` and cannot be subclassed, so we introduce this seam.
 */
interface CodeGenerator
{
    public function generate(int $length): string;
}
