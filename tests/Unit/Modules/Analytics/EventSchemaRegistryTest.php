<?php

declare(strict_types=1);

use Kalaanba\Modules\Analytics\Domain\EventSchema;
use Kalaanba\Modules\Analytics\Domain\EventSchemaRegistry;
use Kalaanba\Modules\Analytics\Domain\Exceptions\UnknownEventSchemaException;
use Kalaanba\Modules\Analytics\Schemas\SchemaCatalogue;

it('stores schemas keyed by name and version', function (): void {
    $registry = new EventSchemaRegistry;
    $registry->register(new EventSchema('health.ping', 1, ['ping_id']));
    $registry->register(new EventSchema('health.ping', 2, ['ping_id', 'tier']));

    expect($registry->has('health.ping', 1))->toBeTrue();
    expect($registry->has('health.ping', 2))->toBeTrue();
    expect($registry->has('health.ping', 3))->toBeFalse();
});

it('returns the requested schema by name and version', function (): void {
    $registry = new EventSchemaRegistry;
    $schema = new EventSchema('match.result_confirmed', 1, ['match_id']);
    $registry->register($schema);

    expect($registry->require('match.result_confirmed', 1))->toBe($schema);
});

it('throws when an unknown schema is requested', function (): void {
    (new EventSchemaRegistry)->require('match.result_confirmed', 1);
})->throws(UnknownEventSchemaException::class);

it('loads every catalogue schema without collision', function (): void {
    $registry = new EventSchemaRegistry;
    SchemaCatalogue::registerAll($registry);

    expect(count($registry->all()))->toBe(count(SchemaCatalogue::all()));
});

it('catalogue contains the canonical health.ping schema', function (): void {
    $registry = new EventSchemaRegistry;
    SchemaCatalogue::registerAll($registry);

    expect($registry->has('health.ping', 1))->toBeTrue();
});
