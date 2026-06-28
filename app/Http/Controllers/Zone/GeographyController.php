<?php

declare(strict_types=1);

namespace App\Http\Controllers\Zone;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Kalaanba\Modules\Zone\Domain\Area;
use Kalaanba\Modules\Zone\Domain\CityHub;
use Kalaanba\Modules\Zone\Domain\GeographyReader;

/**
 * Public Zone geography reads for the area picker (engine doc §5: users choose
 * a City Hub, then an Area). Reference data — no auth required, no PII.
 *
 * - GET /api/v1/zone/hubs
 * - GET /api/v1/zone/areas?city_hub_id=&q=
 *
 * Contracts: contracts/api/zone/get-hubs.v1.yaml, get-areas.v1.yaml.
 * Zone/Belt mapping is intentionally not exposed (admin-derived, §2/§11).
 */
final class GeographyController extends Controller
{
    private const MAX_LIMIT = 200;

    private const DEFAULT_AREA_LIMIT = 100;

    public function __construct(
        private readonly GeographyReader $geography,
    ) {}

    public function hubs(): JsonResponse
    {
        $hubs = $this->geography->listCityHubs();
        $regionNames = $this->resolveRegionNames($hubs);

        $data = array_map(
            fn (CityHub $hub): array => [
                'id' => $hub->id,
                'name' => $hub->name,
                'region' => $regionNames[$hub->regionId] ?? null,
            ],
            $hubs,
        );

        return new JsonResponse([
            'data' => $data,
            'meta' => ['count' => count($data)],
        ]);
    }

    public function areas(Request $request): JsonResponse
    {
        $cityHubId = (string) $request->query('city_hub_id', '');
        if ($cityHubId === '') {
            return $this->error(422, 'zone.city_hub_id_required', 'city_hub_id is required.', $request);
        }

        if ($this->geography->findCityHubById($cityHubId) === null) {
            return $this->error(422, 'zone.city_hub_not_found', 'Unknown city_hub_id.', $request);
        }

        $search = $request->query('q');
        $search = is_string($search) && trim($search) !== '' ? trim($search) : null;

        $areas = array_slice(
            $this->geography->listAreasForCityHub($cityHubId, $search),
            0,
            $this->resolveLimit($request),
        );

        $data = array_map(
            fn (Area $area): array => [
                'id' => $area->id,
                'name' => $area->name,
                'city_hub_id' => $cityHubId,
            ],
            $areas,
        );

        return new JsonResponse([
            'data' => $data,
            'meta' => ['count' => count($data), 'city_hub_id' => $cityHubId],
        ]);
    }

    /**
     * @param  list<CityHub>  $hubs
     * @return array<string, string|null>
     */
    private function resolveRegionNames(array $hubs): array
    {
        $names = [];
        foreach ($hubs as $hub) {
            if (array_key_exists($hub->regionId, $names)) {
                continue;
            }
            $names[$hub->regionId] = $this->geography->findRegionById($hub->regionId)?->name;
        }

        return $names;
    }

    private function resolveLimit(Request $request): int
    {
        $raw = (int) $request->query('limit', (string) self::DEFAULT_AREA_LIMIT);
        if ($raw < 1) {
            return self::DEFAULT_AREA_LIMIT;
        }

        return min($raw, self::MAX_LIMIT);
    }

    private function error(int $status, string $code, string $message, Request $request): JsonResponse
    {
        return new JsonResponse([
            'error' => [
                'code' => $code,
                'message' => $message,
                'details' => [],
                'request_id' => (string) ($request->headers->get('X-Request-Id') ?? ''),
            ],
        ], $status);
    }
}
