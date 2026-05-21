<?php

declare(strict_types=1);

namespace Kalaanba\Modules\Analytics\Application;

use Kalaanba\Modules\Analytics\Domain\AnalyticsEvent;
use Kalaanba\Modules\Analytics\Domain\Contracts\AnalyticsEventWriter;
use Kalaanba\Modules\Analytics\Domain\EventSchemaRegistry;

/**
 * Single entry-point for emitting an analytics event.
 *
 *  - Resolves the registered schema for (event_name, schema_version).
 *  - Rejects unknown schemas (Unknown…Exception).
 *  - Rejects malformed payloads (InvalidEventPropertiesException).
 *  - Delegates persistence to AnalyticsEventWriter.
 *
 * Engineering-standards §8: caller MUST pass a fully-formed AnalyticsEvent;
 * the emitter does not invent fields.
 */
final class AnalyticsEmitter
{
    public function __construct(
        private readonly EventSchemaRegistry $registry,
        private readonly AnalyticsEventWriter $writer,
    ) {}

    public function emit(AnalyticsEvent $event): void
    {
        $schema = $this->registry->require($event->eventName, $event->schemaVersion);
        $schema->validateProperties($event->properties);

        $this->writer->write($event);
    }
}
