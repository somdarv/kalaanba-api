<?php

declare(strict_types=1);

use Kalaanba\Modules\NotificationDistribution\Domain\InboxCursor;
use Kalaanba\Modules\NotificationDistribution\Domain\InboxStatus;

it('marks unread statuses as counting toward the badge', function (): void {
    expect(InboxStatus::Delivered->countsAsUnread())->toBeTrue()
        ->and(InboxStatus::Sent->countsAsUnread())->toBeTrue()
        ->and(InboxStatus::Queued->countsAsUnread())->toBeTrue();
});

it('marks terminal statuses as not counting toward the badge', function (): void {
    expect(InboxStatus::Seen->countsAsUnread())->toBeFalse()
        ->and(InboxStatus::ActedOn->countsAsUnread())->toBeFalse()
        ->and(InboxStatus::Expired->countsAsUnread())->toBeFalse()
        ->and(InboxStatus::Cancelled->countsAsUnread())->toBeFalse();
});

it('identifies terminal statuses', function (): void {
    expect(InboxStatus::Seen->isTerminal())->toBeTrue()
        ->and(InboxStatus::ActedOn->isTerminal())->toBeTrue()
        ->and(InboxStatus::Delivered->isTerminal())->toBeFalse();
});

it('round-trips a cursor through encode/decode', function (): void {
    $original = new InboxCursor('2026-05-25T12:34:56.789012+00:00', '01934f9f-1234-7abc-9def-abcdef012345');
    $encoded = $original->encode();
    $decoded = InboxCursor::decode($encoded);

    expect($decoded)->not->toBeNull()
        ->and($decoded->createdAtIso)->toBe($original->createdAtIso)
        ->and($decoded->id)->toBe($original->id);
});

it('rejects malformed cursors', function (): void {
    expect(InboxCursor::decode('not-base64-!!'))->toBeNull()
        ->and(InboxCursor::decode(base64_encode('no-pipe')))->toBeNull()
        ->and(InboxCursor::decode(base64_encode('|missing-iso')))->toBeNull();
});
