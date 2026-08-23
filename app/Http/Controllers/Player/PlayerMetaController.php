<?php

declare(strict_types=1);

namespace App\Http\Controllers\Player;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Kalaanba\Modules\PlayerAffiliation\Application\PlayerProfileVocabulary;
use Kalaanba\Support\Http\MetaResponse;

/**
 * Vocabulary for the player-profile form: option sets, their labels, and the
 * bounds a profile must respect — all resolved from Admin Configuration at
 * request time (ADR-0007).
 *
 * - GET /api/v1/players/meta
 *
 * Public reference data. It carries no player, no user, and nothing computed
 * (Constitution Law 3), so it needs no auth and can be cached at the edge.
 *
 * The ETag, revalidation and cache-header work moved to
 * {@see MetaResponse} when the club endpoint became its second caller.
 *
 * Contract: contracts/api/player/get-players-meta.v1.yaml.
 */
final class PlayerMetaController extends Controller
{
    public function __construct(
        private readonly PlayerProfileVocabulary $vocabulary,
    ) {}

    public function show(Request $request): JsonResponse
    {
        $view = $this->vocabulary->toMetaView(
            MetaResponse::primaryLanguage($request->headers->get('Accept-Language')),
        );

        return MetaResponse::render($request, $view, 'player.meta.cache_ttl_seconds');
    }
}
