<?php

declare(strict_types=1);

namespace App\Http\Controllers\Club;

use App\Http\Requests\Club\CreateClubRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Str;
use Kalaanba\Modules\Club\Application\ClubNameReserved;
use Kalaanba\Modules\Club\Application\CreateClub;
use Kalaanba\Modules\Club\Domain\Club;
use Kalaanba\Modules\Club\Domain\ClubReader;
use Kalaanba\Support\Config as KxConfig;
use RuntimeException;

/**
 * Club identity — create + "clubs near you" discovery (engine doc §5, §6, §15).
 *
 * - POST /api/v1/clubs           create a club (creator becomes Owner)
 * - GET  /api/v1/clubs?area_id=  list clubs in an area (discovery)
 * - GET  /api/v1/clubs/mine      clubs the caller administers
 *
 * Discovery never shows a club whose `professional` claim is still pending; that
 * filter lives in the reader, not here (ADR-0017). `mine` deliberately does,
 * so an Owner can watch their own claim being reviewed.
 *
 * Contracts: contracts/api/club/{post,get}-clubs.v1.yaml.
 */
final class ClubController extends Controller
{
    public function __construct(
        private readonly CreateClub $create,
        private readonly ClubReader $reader,
    ) {}

    public function store(CreateClubRequest $request): JsonResponse
    {
        $user = $request->user();
        if (! $user instanceof User) {
            return $this->error(401, 'auth.unauthenticated', 'Authentication required.', $request);
        }

        $validated = $request->validated();
        $crest = $validated['crest_url'] ?? null;

        try {
            $club = $this->create->execute(
                name: (string) $validated['name'],
                clubType: (string) $validated['club_type'],
                tier: $request->tier(),
                cityHubId: (string) $validated['city_hub_id'],
                areaId: (string) $validated['area_id'],
                crestUrl: is_string($crest) && $crest !== '' ? $crest : null,
                createdByUserId: (string) $user->getAuthIdentifier(),
            );
        } catch (ClubNameReserved) {
            // Which club the name belongs to is deliberately NOT returned. It
            // would confirm to whoever is probing exactly what is on the list,
            // and the person who typed it already knows who Asante Kotoko are.
            // The exception carries it for the audit trail; the response does not.
            return $this->error(422, 'club.name_reserved', 'That club name is not available.', $request);
        } catch (RuntimeException $e) {
            return $this->error(422, 'club.location_unknown', $e->getMessage(), $request);
        }

        return new JsonResponse(
            ['data' => $this->present($club, forOwnAdmins: true), 'meta' => []],
            201,
        );
    }

    public function index(Request $request): JsonResponse
    {
        if (! $request->user() instanceof User) {
            return $this->error(401, 'auth.unauthenticated', 'Authentication required.', $request);
        }

        $areaId = (string) $request->query('area_id', '');
        if ($areaId === '' || ! Str::isUuid($areaId)) {
            return $this->error(422, 'club.area_required', 'A valid area_id query parameter is required.', $request);
        }

        $limit = $this->configInt('club.near_you.page_size', 25);
        $clubs = $this->reader->listByArea($areaId, $limit);

        return new JsonResponse([
            'data' => array_map(fn (Club $c): array => $this->present($c), $clubs),
            'meta' => ['count' => count($clubs), 'area_id' => $areaId],
        ], 200);
    }

    public function mine(Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $user instanceof User) {
            return $this->error(401, 'auth.unauthenticated', 'Authentication required.', $request);
        }

        $clubs = $this->reader->listAdminClubsForUser((string) $user->getAuthIdentifier());

        return new JsonResponse([
            'data' => array_map(fn (Club $c): array => $this->present($c, forOwnAdmins: true), $clubs),
            'meta' => ['count' => count($clubs)],
        ], 200);
    }

    /**
     * @return array<string, mixed>
     */
    private function present(Club $club, bool $forOwnAdmins = false): array
    {
        return [
            'id' => $club->id,
            'name' => $club->name,
            'club_type' => $club->clubType,
            'city_hub_id' => $club->cityHubId,
            'area_id' => $club->areaId,
            'crest_url' => $club->crestUrl,
            'maturity_level' => $club->maturity->value,
            'verification_state' => $club->verificationState->value,
            // The SOURCE is internal (§4): the public badge says "Verified
            // Club" and never how the club got there. It is presented only to
            // the club's own admins, on `mine` and on the creation response.
            'verification_source' => $forOwnAdmins ? $club->verificationSource?->value : null,
        ];
    }

    private function configInt(string $key, int $fallback): int
    {
        try {
            $value = KxConfig::get($key);

            return $value === null ? $fallback : (int) $value->value;
        } catch (\Throwable) {
            return $fallback;
        }
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
