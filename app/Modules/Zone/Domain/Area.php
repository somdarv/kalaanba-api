<?php

declare(strict_types=1);

namespace Kalaanba\Modules\Zone\Domain;

/**
 * Readonly view of an Area (actual locality / quarter / settlement).
 *
 * Engine doc §5 — users select Area first; the system maps it to a Zone.
 */
final readonly class Area
{
    public function __construct(
        public string $id,
        public string $zoneId,
        public string $code,
        public string $name,
    ) {}
}
