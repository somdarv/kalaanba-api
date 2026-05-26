<?php

declare(strict_types=1);

namespace Kalaanba\Modules\Season\Application;

use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Contracts\Cache\Repository as Cache;
use Kalaanba\Modules\Season\Domain\SeasonCalendar;
use Kalaanba\Modules\Season\Domain\SeasonConfigProvider;
use Kalaanba\Modules\Season\Domain\SeasonRepository;
use Kalaanba\Modules\Season\Domain\SeasonView;

/**
 * The single public read port for the Season engine. Every other engine
 * (RP Economy, Challenge, Standings, …) MUST go through this — never
 * directly query the `seasons` table (Constitution §1.1).
 *
 * Engine doc: docs/engines/season/Season_Engine_UPDATED.md §11 (read API).
 */
final class CurrentSeason
{
    private const CACHE_TTL_SECONDS = 300; // 5 minutes — phases change at most daily.

    public function __construct(
        private readonly SeasonRepository $repository,
        private readonly SeasonConfigProvider $configLoader,
        private readonly Cache $cache,
    ) {}

    public function at(?DateTimeImmutable $instant = null): SeasonView
    {
        $instant ??= new DateTimeImmutable('now', new DateTimeZone('UTC'));
        $key = sprintf('kx:season:current:v1:%s', $instant->format('Ymd'));

        return $this->cache->remember(
            $key,
            self::CACHE_TTL_SECONDS,
            fn (): SeasonView => $this->resolve($instant),
        );
    }

    /**
     * Bypass cache — used by the `season:tick` command after writes.
     */
    public function forget(): void
    {
        $today = (new DateTimeImmutable('now', new DateTimeZone('UTC')))->format('Ymd');
        $this->cache->forget(sprintf('kx:season:current:v1:%s', $today));
    }

    private function resolve(DateTimeImmutable $instant): SeasonView
    {
        $persisted = $this->repository->findContaining($instant);
        if ($persisted !== null) {
            return $persisted;
        }

        // Fallback: derive on the fly. Useful pre-seed in CI/tests.
        $calendar = new SeasonCalendar($this->configLoader->load());
        $window = $calendar->windowFor($instant);
        $phase = $calendar->phaseAt($window, $instant);

        return $this->repository->upsertFromWindow($window, $phase);
    }
}
