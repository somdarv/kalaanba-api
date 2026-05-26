<?php

declare(strict_types=1);

namespace Kalaanba\Modules\Zone\Domain;

/** Engine doc §2 — broad geographic context (e.g. "Northern Region"). */
final readonly class Region
{
    public function __construct(
        public string $id,
        public string $countryId,
        public string $code,
        public string $name,
    ) {}
}
