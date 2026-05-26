<?php

declare(strict_types=1);

namespace Kalaanba\Modules\Zone\Domain;

/**
 * Readonly view of a Zone (or Belt).
 *
 * Engine doc §2 — Belt is a Zone-type for outer / peri-urban communities;
 * we model both as `Zone` with `kind` discriminator (single table).
 */
final readonly class Zone
{
    public function __construct(
        public string $id,
        public string $cityHubId,
        public ZoneKind $kind,
        public string $code,
        public string $name,
    ) {}
}
