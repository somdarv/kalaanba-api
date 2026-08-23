<?php

declare(strict_types=1);

namespace Kalaanba\Support\Http;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Kalaanba\Support\Config as KxConfig;
use Throwable;

/**
 * The caching and content-negotiation half of a per-engine `/meta` endpoint
 * (ADR-0007): ETag fingerprinting, `If-None-Match` revalidation,
 * `Cache-Control` from a config-owned TTL, and the `Accept-Language` narrowing
 * that decides which label map a vocabulary resolves.
 *
 * Extracted when the club endpoint became the second caller. The rule of three
 * normally says wait, but ADR-0007 states in as many words that this endpoint
 * shape recurs across every engine — "whatever we do here we will do eighteen
 * more times" — so the third caller is foreseeable rather than hypothetical,
 * and eighteen copies of an ETag comparison is eighteen chances to get the
 * weak-validator prefix wrong in one of them.
 *
 * Holds no engine knowledge. It is handed a resolved view and a TTL config key
 * and does the HTTP part; what goes in the view belongs to the engine's own
 * vocabulary class.
 */
final class MetaResponse
{
    private const DEFAULT_CACHE_TTL_SECONDS = 300;

    /**
     * Render a vocabulary as either `200` with the body or `304` with none.
     *
     * @param  array<string, mixed>  $view  The resolved vocabulary.
     * @param  string  $ttlConfigKey  Engine's own `*.meta.cache_ttl_seconds` key.
     */
    public static function render(Request $request, array $view, string $ttlConfigKey): JsonResponse
    {
        $etag = self::fingerprint($view);
        $headers = self::cacheHeaders($etag, $ttlConfigKey);

        if (self::matchesEtag($request->headers->get('If-None-Match'), $etag)) {
            return (new JsonResponse(null, 304))->withHeaders($headers);
        }

        return (new JsonResponse(['data' => $view, 'meta' => []], 200))->withHeaders($headers);
    }

    /**
     * First language tag from Accept-Language ("en-GB,en;q=0.9" → "en-gb").
     * Labels only — the header never influences a key or the option order
     * (Constitution Law 4).
     */
    public static function primaryLanguage(?string $header): ?string
    {
        if ($header === null || trim($header) === '') {
            return null;
        }

        $first = trim((string) explode(',', $header)[0]);
        $tag = strtolower(trim((string) explode(';', $first)[0]));

        return preg_match('/^[a-z]{2,3}(-[a-z0-9]{2,8})?$/', $tag) === 1 ? $tag : null;
    }

    /**
     * Changes whenever any sourced config key changes, which is what lets a
     * client hold the vocabulary indefinitely and revalidate cheaply.
     *
     * @param  array<string, mixed>  $view
     */
    private static function fingerprint(array $view): string
    {
        $encoded = json_encode($view);

        return '"'.hash('sha256', $encoded === false ? '' : $encoded).'"';
    }

    private static function matchesEtag(?string $header, string $etag): bool
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
    private static function cacheHeaders(string $etag, string $ttlConfigKey): array
    {
        $ttl = self::cacheTtlSeconds($ttlConfigKey);

        return [
            'ETag' => $etag,
            'Cache-Control' => sprintf(
                'public, max-age=%d, stale-while-revalidate=%d',
                $ttl,
                $ttl * 12,
            ),
            'Vary' => 'Accept-Language',
        ];
    }

    private static function cacheTtlSeconds(string $key): int
    {
        try {
            $value = KxConfig::get($key);
        } catch (Throwable) {
            return self::DEFAULT_CACHE_TTL_SECONDS;
        }

        if ($value === null || ! is_numeric($value->value)) {
            return self::DEFAULT_CACHE_TTL_SECONDS;
        }

        $ttl = (int) $value->value;

        return $ttl > 0 ? $ttl : self::DEFAULT_CACHE_TTL_SECONDS;
    }
}
