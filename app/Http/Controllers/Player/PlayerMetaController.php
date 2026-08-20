<?php

declare(strict_types=1);

namespace App\Http\Controllers\Player;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Kalaanba\Modules\PlayerAffiliation\Application\PlayerProfileVocabulary;
use Kalaanba\Support\Config as KxConfig;
use Throwable;

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
 * Contract: contracts/api/player/get-players-meta.v1.yaml.
 */
final class PlayerMetaController extends Controller
{
    private const DEFAULT_CACHE_TTL_SECONDS = 300;

    public function __construct(
        private readonly PlayerProfileVocabulary $vocabulary,
    ) {}

    public function show(Request $request): JsonResponse
    {
        $view = $this->vocabulary->toMetaView(
            $this->primaryLanguage($request->headers->get('Accept-Language')),
        );

        $etag = $this->fingerprint($view);

        if ($this->matchesEtag($request->headers->get('If-None-Match'), $etag)) {
            return (new JsonResponse(null, 304))->withHeaders($this->cacheHeaders($etag));
        }

        return (new JsonResponse(['data' => $view, 'meta' => []], 200))
            ->withHeaders($this->cacheHeaders($etag));
    }

    /**
     * Changes whenever any sourced config key changes, which is what lets a
     * client hold this indefinitely and revalidate cheaply.
     *
     * @param  array<string, mixed>  $view
     */
    private function fingerprint(array $view): string
    {
        $encoded = json_encode($view);

        return '"'.hash('sha256', $encoded === false ? '' : $encoded).'"';
    }

    private function matchesEtag(?string $header, string $etag): bool
    {
        if ($header === null || trim($header) === '') {
            return false;
        }

        foreach (explode(',', $header) as $candidate) {
            $candidate = trim($candidate);
            // Tolerate the weak-validator prefix a proxy may add.
            if ($candidate === $etag || ltrim($candidate, 'W/') === $etag) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array<string, string>
     */
    private function cacheHeaders(string $etag): array
    {
        return [
            'ETag' => $etag,
            'Cache-Control' => sprintf(
                'public, max-age=%d, stale-while-revalidate=%d',
                $ttl = $this->cacheTtlSeconds(),
                $ttl * 12,
            ),
            'Vary' => 'Accept-Language',
        ];
    }

    private function cacheTtlSeconds(): int
    {
        try {
            $value = KxConfig::get('player.meta.cache_ttl_seconds');
        } catch (Throwable) {
            return self::DEFAULT_CACHE_TTL_SECONDS;
        }

        if ($value === null || ! is_numeric($value->value)) {
            return self::DEFAULT_CACHE_TTL_SECONDS;
        }

        $ttl = (int) $value->value;

        return $ttl > 0 ? $ttl : self::DEFAULT_CACHE_TTL_SECONDS;
    }

    /**
     * First language tag from Accept-Language ("en-GB,en;q=0.9" → "en-gb").
     * Labels only — the header never influences a key or the option order.
     */
    private function primaryLanguage(?string $header): ?string
    {
        if ($header === null || trim($header) === '') {
            return null;
        }

        $first = trim((string) explode(',', $header)[0]);
        $tag = strtolower(trim((string) explode(';', $first)[0]));

        return preg_match('/^[a-z]{2,3}(-[a-z0-9]{2,8})?$/', $tag) === 1 ? $tag : null;
    }
}
