<?php

declare(strict_types=1);

namespace Kalaanba\Modules\Zone\Domain;

/** Engine doc §2 — practical football center, NOT a municipal boundary. */
final readonly class CityHub
{
    public function __construct(
        public string $id,
        public string $regionId,
        public string $code,
        public string $name,
    ) {}
}
