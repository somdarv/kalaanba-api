<?php

declare(strict_types=1);

use Kalaanba\Support\Auth\PhoneHash;

it('produces deterministic hashes for the same phone number', function (): void {
    $hasher = new PhoneHash('test-secret');

    expect($hasher->hash('+233244123456'))->toBe($hasher->hash('+233244123456'));
});

it('produces different hashes when the secret changes', function (): void {
    $a = new PhoneHash('secret-a');
    $b = new PhoneHash('secret-b');

    expect($a->hash('+233244123456'))->not->toBe($b->hash('+233244123456'));
});

it('extracts the last four digits', function (): void {
    expect((new PhoneHash('s'))->last4('+233244123456'))->toBe('3456');
});

it('masks the phone leaving only the last four digits visible', function (): void {
    $masked = (new PhoneHash('s'))->mask('+233244123456');

    expect($masked)->toEndWith('3456')
        ->and(substr($masked, 0, -4))->toMatch('/^\*+$/');
});
