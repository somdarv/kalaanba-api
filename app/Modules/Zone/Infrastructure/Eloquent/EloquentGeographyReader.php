<?php

declare(strict_types=1);

namespace Kalaanba\Modules\Zone\Infrastructure\Eloquent;

use Kalaanba\Modules\Zone\Domain\Area;
use Kalaanba\Modules\Zone\Domain\CityHub;
use Kalaanba\Modules\Zone\Domain\Country;
use Kalaanba\Modules\Zone\Domain\GeographyReader;
use Kalaanba\Modules\Zone\Domain\Region;
use Kalaanba\Modules\Zone\Domain\Zone;
use Kalaanba\Modules\Zone\Domain\ZoneKind;

final class EloquentGeographyReader implements GeographyReader
{
    public function findCountryByCode(string $code): ?Country
    {
        /** @var CountryRecord|null $row */
        $row = CountryRecord::query()->where('code', strtoupper($code))->first();

        return $row === null ? null : new Country(
            id: (string) $row->getAttribute('id'),
            code: (string) $row->getAttribute('code'),
            name: (string) $row->getAttribute('name'),
        );
    }

    public function findRegionById(string $id): ?Region
    {
        /** @var RegionRecord|null $row */
        $row = RegionRecord::query()->find($id);

        return $row === null ? null : new Region(
            id: (string) $row->getAttribute('id'),
            countryId: (string) $row->getAttribute('country_id'),
            code: (string) $row->getAttribute('code'),
            name: (string) $row->getAttribute('name'),
        );
    }

    public function findCityHubById(string $id): ?CityHub
    {
        /** @var CityHubRecord|null $row */
        $row = CityHubRecord::query()->find($id);

        return $row === null ? null : new CityHub(
            id: (string) $row->getAttribute('id'),
            regionId: (string) $row->getAttribute('region_id'),
            code: (string) $row->getAttribute('code'),
            name: (string) $row->getAttribute('name'),
        );
    }

    public function findZoneById(string $id): ?Zone
    {
        /** @var ZoneRecord|null $row */
        $row = ZoneRecord::query()->find($id);

        return $row === null ? null : $this->mapZone($row);
    }

    public function findAreaById(string $id): ?Area
    {
        /** @var AreaRecord|null $row */
        $row = AreaRecord::query()->find($id);

        return $row === null ? null : new Area(
            id: (string) $row->getAttribute('id'),
            zoneId: (string) $row->getAttribute('zone_id'),
            code: (string) $row->getAttribute('code'),
            name: (string) $row->getAttribute('name'),
        );
    }

    public function listZonesForCityHub(string $cityHubId): array
    {
        return ZoneRecord::query()
            ->where('city_hub_id', $cityHubId)
            ->orderBy('kind')
            ->orderBy('name')
            ->get()
            ->map(fn (ZoneRecord $r): Zone => $this->mapZone($r))
            ->values()
            ->all();
    }

    public function listAreasForCityHub(string $cityHubId): array
    {
        $zoneIds = ZoneRecord::query()->where('city_hub_id', $cityHubId)->pluck('id');

        return AreaRecord::query()
            ->whereIn('zone_id', $zoneIds)
            ->orderBy('name')
            ->get()
            ->map(fn (AreaRecord $r): Area => new Area(
                id: (string) $r->getAttribute('id'),
                zoneId: (string) $r->getAttribute('zone_id'),
                code: (string) $r->getAttribute('code'),
                name: (string) $r->getAttribute('name'),
            ))
            ->values()
            ->all();
    }

    private function mapZone(ZoneRecord $row): Zone
    {
        return new Zone(
            id: (string) $row->getAttribute('id'),
            cityHubId: (string) $row->getAttribute('city_hub_id'),
            kind: ZoneKind::from((string) $row->getAttribute('kind')),
            code: (string) $row->getAttribute('code'),
            name: (string) $row->getAttribute('name'),
        );
    }
}
