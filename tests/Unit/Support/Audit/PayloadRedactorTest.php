<?php

declare(strict_types=1);

use Kalaanba\Support\Audit\PayloadRedactor;

it('replaces values of sensitive keys with [redacted]', function (): void {
    $redactor = new PayloadRedactor;

    $out = $redactor->redact([
        'name' => 'Ama',
        'password' => 'super-secret',
        'phone_e164' => '+233244000000',
        'otp' => '123456',
        'token' => 'abc',
        'api_key' => 'k',
        'nested' => [
            'cvv' => '999',
            'safe' => 'ok',
        ],
    ]);

    expect($out['name'])->toBe('Ama')
        ->and($out['password'])->toBe('[redacted]')
        ->and($out['phone_e164'])->toBe('[redacted]')
        ->and($out['otp'])->toBe('[redacted]')
        ->and($out['token'])->toBe('[redacted]')
        ->and($out['api_key'])->toBe('[redacted]')
        ->and($out['nested']['cvv'])->toBe('[redacted]')
        ->and($out['nested']['safe'])->toBe('ok');
});

it('matches sensitive keys case-insensitively and via substrings', function (): void {
    $out = (new PayloadRedactor)->redact([
        'Authorization' => 'Bearer xyz',
        'user_password_confirmation' => 'x',
        'access_token' => 'tok',
    ]);

    expect($out['Authorization'])->toBe('[redacted]')
        ->and($out['user_password_confirmation'])->toBe('[redacted]')
        ->and($out['access_token'])->toBe('[redacted]');
});

it('leaves non-sensitive payloads untouched', function (): void {
    $out = (new PayloadRedactor)->redact([
        'club_id' => 'uuid',
        'score' => ['home' => 2, 'away' => 1],
    ]);

    expect($out)->toBe([
        'club_id' => 'uuid',
        'score' => ['home' => 2, 'away' => 1],
    ]);
});
