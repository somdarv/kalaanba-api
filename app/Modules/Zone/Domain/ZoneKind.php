<?php

declare(strict_types=1);

namespace Kalaanba\Modules\Zone\Domain;

/**
 * Discriminator for a competitive division inside a City Hub.
 *
 * Engine doc: docs/engines/zone/Zone_Engine_UPDATED.md §2.
 *  - Zone : core competitive division (e.g. "North Zone").
 *  - Belt : Zone-type for outer / peri-urban communities (e.g. "North-East Belt").
 *
 * Belts compete on the same plane as zones; the distinction is semantic.
 * Per ADR (single table with kind discriminator) — engine doc §2 lists
 * Belt as a Zone-type, not a separate hierarchy level.
 */
enum ZoneKind: string
{
    case Zone = 'zone';
    case Belt = 'belt';
}
