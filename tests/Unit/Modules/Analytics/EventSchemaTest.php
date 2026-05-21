<?php

declare(strict_types=1);

use Kalaanba\Modules\Analytics\Domain\EventSchema;
use Kalaanba\Modules\Analytics\Domain\Exceptions\InvalidEventPropertiesException;

it('accepts an event_name matching the engine.action pattern', function (): void {
    $schema = new EventSchema('match.result_confirmed', 1, ['match_id']);

    expect($schema->key())->toBe('match.result_confirmed@v1');
});

it('rejects an event_name without a dot', function (): void {
    new EventSchema('matchresultconfirmed', 1);
})->throws(InvalidArgumentException::class);

it('rejects an event_name with uppercase characters', function (): void {
    new EventSchema('Match.result_confirmed', 1);
})->throws(InvalidArgumentException::class);

it('rejects a schema_version less than 1', function (): void {
    new EventSchema('health.ping', 0);
})->throws(InvalidArgumentException::class);

it('rejects when a property is declared required and optional', function (): void {
    new EventSchema('health.ping', 1, ['ping_id'], ['ping_id']);
})->throws(InvalidArgumentException::class);

it('passes validation when all required keys are present', function (): void {
    $schema = new EventSchema('health.ping', 1, ['ping_id']);
    $schema->validateProperties(['ping_id' => 'abc']);
})->throwsNoExceptions();

it('rejects missing required keys', function (): void {
    $schema = new EventSchema('health.ping', 1, ['ping_id']);
    $schema->validateProperties([]);
})->throws(InvalidEventPropertiesException::class, 'ping_id');

it('rejects unknown keys to surface payload drift', function (): void {
    $schema = new EventSchema('health.ping', 1, ['ping_id']);
    $schema->validateProperties(['ping_id' => 'abc', 'sneaky' => 1]);
})->throws(InvalidEventPropertiesException::class, 'sneaky');

it('accepts optional keys', function (): void {
    $schema = new EventSchema('health.ping', 1, ['ping_id'], ['note']);
    $schema->validateProperties(['ping_id' => 'abc', 'note' => 'ok']);
})->throwsNoExceptions();
