<?php

declare(strict_types=1);

namespace Kalaanba\Modules\Analytics\Domain\Contracts;

use Kalaanba\Modules\Analytics\Domain\AnalyticsEvent;

/**
 * Port for persisting a validated analytics event.
 *
 * Production binding writes to analytics.events (Postgres, daily partitions).
 * Tests bind in-memory implementations.
 */
interface AnalyticsEventWriter
{
    public function write(AnalyticsEvent $event): void;
}
