<?php

declare(strict_types=1);

namespace App\Http\Controllers\Club;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Kalaanba\Modules\Club\Application\ClubVocabulary;
use Kalaanba\Support\Http\MetaResponse;

/**
 * Vocabulary for the club-creation flow: the two tiers, the club types and
 * which tier each belongs to, their labels, and the name bounds the form must
 * enforce — all resolved from Admin Configuration at request time (ADR-0007).
 *
 * - GET /api/v1/clubs/meta
 *
 * Public reference data: no club, no user, nothing computed (Law 3). It
 * replaces the hardcoded `CLUB_TYPE_LABELS` the frontend was carrying, which
 * was a compiled-in mirror of a config default.
 *
 * **It does not serve the reserved-name list.** Whether a name may be used is a
 * verdict and verdicts are backend truth; a client copy of a value that changes
 * without a deploy is stale by construction; and publishing the list publishes
 * the map for routing around it (ADR-0017 §4).
 *
 * Contract: contracts/api/club/get-clubs-meta.v1.yaml.
 */
final class ClubMetaController extends Controller
{
    public function __construct(
        private readonly ClubVocabulary $vocabulary,
    ) {}

    public function show(Request $request): JsonResponse
    {
        $view = $this->vocabulary->toMetaView(
            MetaResponse::primaryLanguage($request->headers->get('Accept-Language')),
        );

        return MetaResponse::render($request, $view, 'club.meta.cache_ttl_seconds');
    }
}
