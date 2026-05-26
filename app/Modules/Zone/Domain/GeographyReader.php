<?php

declare(strict_types=1);

namespace Kalaanba\Modules\Zone\Domain;

/**
 * Read-side port for the Zone-engine hierarchy.
 *
 * All entities are admin-managed (Constitution §1.10). Mutations happen
 * via admin tooling + seeders, NOT through this port.
 */
interface GeographyReader
{
    public function findCountryByCode(string $code): ?Country;

    public function findRegionById(string $id): ?Region;

    public function findCityHubById(string $id): ?CityHub;

    public function findZoneById(string $id): ?Zone;

    public function findAreaById(string $id): ?Area;

    /** @return list<Zone> */
    public function listZonesForCityHub(string $cityHubId): array;

    /** @return list<Area> */
    public function listAreasForCityHub(string $cityHubId): array;
}
