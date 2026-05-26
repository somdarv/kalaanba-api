<?php

declare(strict_types=1);

namespace Kalaanba\Modules\Zone\Domain;

/** Engine doc §2 — locked hierarchy. */
final readonly class Country
{
    public function __construct(
        public string $id,
        public string $code,   // ISO-3166-1 alpha-2
        public string $name,
    ) {}
}
