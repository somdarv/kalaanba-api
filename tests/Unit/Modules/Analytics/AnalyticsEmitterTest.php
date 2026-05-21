<?php

declare(strict_types=1);

use Kalaanba\Modules\Analytics\Application\AnalyticsEmitter;
use Kalaanba\Modules\Analytics\Domain\AnalyticsEvent;
use Kalaanba\Modules\Analytics\Domain\Contracts\AnalyticsEventWriter;
use Kalaanba\Modules\Analytics\Domain\EventSchema;
use Kalaanba\Modules\Analytics\Domain\EventSchemaRegistry;
use Kalaanba\Modules\Analytics\Domain\Exceptions\InvalidEventPropertiesException;
use Kalaanba\Modules\Analytics\Domain\Exceptions\UnknownEventSchemaException;

/**
 * In-memory writer that captures every persisted event for assertions.
 */
final class InMemoryAnalyticsEventWriter implements AnalyticsEventWriter
{
    /** @var list<AnalyticsEvent> */
    public array $written = [];

    public function write(AnalyticsEvent $event): void
    {
        $this->written[] = $event;
    }
}

function makeRegistryWithHealthPing(): EventSchemaRegistry
{
    $registry = new EventSchemaRegistry;
    $registry->register(new EventSchema('health.ping', 1, ['ping_id']));

    return $registry;
}

it('writes a valid event through to the writer', function (): void {
    $writer = new InMemoryAnalyticsEventWriter;
    $emitter = new AnalyticsEmitter(makeRegistryWithHealthPing(), $writer);

    $emitter->emit(new AnalyticsEvent(
        eventId: 'evt-1',
        eventName: 'health.ping',
        schemaVersion: 1,
        occurredAt: new DateTimeImmutable('2026-05-20T10:00:00Z'),
        source: 'health',
        properties: ['ping_id' => 'p-1'],
    ));

    expect($writer->written)->toHaveCount(1);
    expect($writer->written[0]->eventName)->toBe('health.ping');
});

it('rejects an unknown event_name + version pair', function (): void {
    $writer = new InMemoryAnalyticsEventWriter;
    $emitter = new AnalyticsEmitter(makeRegistryWithHealthPing(), $writer);

    $emitter->emit(new AnalyticsEvent(
        eventId: 'evt-2',
        eventName: 'health.ping',
        schemaVersion: 99,
        occurredAt: new DateTimeImmutable,
        source: 'health',
        properties: ['ping_id' => 'p-2'],
    ));
})->throws(UnknownEventSchemaException::class);

it('rejects payloads that violate the registered schema', function (): void {
    $writer = new InMemoryAnalyticsEventWriter;
    $emitter = new AnalyticsEmitter(makeRegistryWithHealthPing(), $writer);

    $emitter->emit(new AnalyticsEvent(
        eventId: 'evt-3',
        eventName: 'health.ping',
        schemaVersion: 1,
        occurredAt: new DateTimeImmutable,
        source: 'health',
        properties: [], // missing ping_id
    ));
})->throws(InvalidEventPropertiesException::class);

it('does not write when validation fails', function (): void {
    $writer = new InMemoryAnalyticsEventWriter;
    $emitter = new AnalyticsEmitter(makeRegistryWithHealthPing(), $writer);

    try {
        $emitter->emit(new AnalyticsEvent(
            eventId: 'evt-4',
            eventName: 'health.ping',
            schemaVersion: 1,
            occurredAt: new DateTimeImmutable,
            source: 'health',
            properties: ['ping_id' => 'p-4', 'rogue' => 'x'],
        ));
    } catch (InvalidEventPropertiesException) {
        // expected
    }

    expect($writer->written)->toBeEmpty();
});
