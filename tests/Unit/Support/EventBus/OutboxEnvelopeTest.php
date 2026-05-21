<?php

declare(strict_types=1);
use Kalaanba\Support\EventBus\OutboxEnvelope;

// ---------------------------------------------------------------------------
// Valid construction
// ---------------------------------------------------------------------------

it('creates an envelope with valid engine.action event name', function (): void {
    $envelope = new OutboxEnvelope(
        eventId: 'a1b2c3d4-0000-0000-0000-000000000001',
        eventName: 'match.result_confirmed',
        schemaVersion: 1,
        occurredAt: new DateTimeImmutable('2026-05-20T10:00:00Z'),
        actorId: null,
        actorRole: 'system',
        source: 'match',
        payload: ['match_id' => 'xyz'],
    );

    expect($envelope->eventName)->toBe('match.result_confirmed');
    expect($envelope->schemaVersion)->toBe(1);
});

it('accepts multi-segment snake_case event names', function (): void {
    $envelope = new OutboxEnvelope(
        eventId: 'a1b2c3d4-0000-0000-0000-000000000002',
        eventName: 'rp_economy.stake_locked',
        schemaVersion: 2,
        occurredAt: new DateTimeImmutable,
        actorId: 'player-uuid',
        actorRole: 'player',
        source: 'rp_economy',
        payload: [],
    );

    expect($envelope->eventName)->toBe('rp_economy.stake_locked');
});

// ---------------------------------------------------------------------------
// Validation — event name format
// ---------------------------------------------------------------------------

it('rejects an event name with no dot separator', function (): void {
    new OutboxEnvelope(
        eventId: 'a1b2c3d4-0000-0000-0000-000000000003',
        eventName: 'matchresultconfirmed',
        schemaVersion: 1,
        occurredAt: new DateTimeImmutable,
        actorId: null,
        actorRole: null,
        source: 'match',
        payload: [],
    );
})->throws(InvalidArgumentException::class);

it('rejects an event name with uppercase letters', function (): void {
    new OutboxEnvelope(
        eventId: 'a1b2c3d4-0000-0000-0000-000000000004',
        eventName: 'Match.result_confirmed',
        schemaVersion: 1,
        occurredAt: new DateTimeImmutable,
        actorId: null,
        actorRole: null,
        source: 'match',
        payload: [],
    );
})->throws(InvalidArgumentException::class);

it('rejects an event name with multiple dots', function (): void {
    new OutboxEnvelope(
        eventId: 'a1b2c3d4-0000-0000-0000-000000000005',
        eventName: 'match.result.confirmed',
        schemaVersion: 1,
        occurredAt: new DateTimeImmutable,
        actorId: null,
        actorRole: null,
        source: 'match',
        payload: [],
    );
})->throws(InvalidArgumentException::class);

it('rejects an event name that starts with a dot', function (): void {
    new OutboxEnvelope(
        eventId: 'a1b2c3d4-0000-0000-0000-000000000006',
        eventName: '.result_confirmed',
        schemaVersion: 1,
        occurredAt: new DateTimeImmutable,
        actorId: null,
        actorRole: null,
        source: 'match',
        payload: [],
    );
})->throws(InvalidArgumentException::class);

// ---------------------------------------------------------------------------
// Validation — schema_version
// ---------------------------------------------------------------------------

it('rejects schema_version less than 1', function (): void {
    new OutboxEnvelope(
        eventId: 'a1b2c3d4-0000-0000-0000-000000000007',
        eventName: 'health.ping',
        schemaVersion: 0,
        occurredAt: new DateTimeImmutable,
        actorId: null,
        actorRole: null,
        source: 'health',
        payload: [],
    );
})->throws(InvalidArgumentException::class);

// ---------------------------------------------------------------------------
// toPayloadArray()
// ---------------------------------------------------------------------------

it('serialises all fields in toPayloadArray', function (): void {
    $occurredAt = new DateTimeImmutable('2026-05-20T12:00:00+00:00');

    $envelope = new OutboxEnvelope(
        eventId: 'abc-def',
        eventName: 'health.ping',
        schemaVersion: 1,
        occurredAt: $occurredAt,
        actorId: 'actor-1',
        actorRole: 'admin',
        source: 'health',
        payload: ['ping_id' => 'abc-def'],
    );

    $arr = $envelope->toPayloadArray();

    expect($arr['event_id'])->toBe('abc-def')
        ->and($arr['event_name'])->toBe('health.ping')
        ->and($arr['schema_version'])->toBe(1)
        ->and($arr['actor_id'])->toBe('actor-1')
        ->and($arr['actor_role'])->toBe('admin')
        ->and($arr['source'])->toBe('health')
        ->and($arr['payload'])->toBe(['ping_id' => 'abc-def'])
        ->and($arr['occurred_at'])->toBe($occurredAt->format(DateTimeImmutable::ATOM));
});
