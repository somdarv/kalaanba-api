<?php

declare(strict_types=1);

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Kalaanba\Support\Auth\Otp\Exceptions\OtpDeliveryFailedException;
use Kalaanba\Support\Auth\Otp\SmsOnlineGhOtpProvider;
use Tests\TestCase;

/*
 * tests/Pest.php binds TestCase only for Feature. This file needs it because the
 * driver talks through the Http and Log facades, which require a booted
 * container — Http::fake() has nothing to swap otherwise. Bound here per-file
 * rather than widening the Unit binding for everything.
 */
uses(TestCase::class);

/**
 * The value of this driver is entirely in how it reads failure, because this
 * gateway reports several failures as HTTP 200. A test suite that only asserts
 * the happy path proves nothing except that we agree with ourselves.
 */
function makeProvider(string $apiKey = 'test-key'): SmsOnlineGhOtpProvider
{
    return new SmsOnlineGhOtpProvider(
        apiKey: $apiKey,
        senderId: 'Kalaanba',
        baseUrl: 'https://api.smsonlinegh.com',
        timeoutSeconds: 10,
        messageTemplate: 'Your Kalaanba code is {code}. It expires in 5 minutes.',
    );
}

it('posts form-encoded, never JSON, because the v5 API cannot parse JSON', function (): void {
    Http::fake(['*' => Http::response(['handshake' => ['label' => 'HSHK_OK'], 'data' => ['batch' => 'b1']], 200)]);

    makeProvider()->send('+233244123456', '123456');

    Http::assertSent(function (Request $request): bool {
        expect($request->hasHeader('Content-Type', 'application/x-www-form-urlencoded'))->toBeTrue();
        expect($request->url())->toBe('https://api.smsonlinegh.com/v5/message/sms/send');

        return true;
    });
});

it('strips the leading + because the gateway takes 233… not +233…', function (): void {
    Http::fake(['*' => Http::response(['handshake' => ['label' => 'HSHK_OK']], 200)]);

    makeProvider()->send('+233244123456', '123456');

    Http::assertSent(fn (Request $r): bool => $r['to'] === '233244123456');
});

it('substitutes the code into the configured template and sends type 0', function (): void {
    Http::fake(['*' => Http::response(['handshake' => ['label' => 'HSHK_OK']], 200)]);

    makeProvider()->send('+233244123456', '987654');

    Http::assertSent(function (Request $r): bool {
        expect($r['text'])->toBe('Your Kalaanba code is 987654. It expires in 5 minutes.');
        expect($r['type'])->toBe(0);
        expect($r['sender'])->toBe('Kalaanba');
        expect($r['key'])->toBe('test-key');

        return true;
    });
});

it('treats an auth failure as a failure even though it arrives as HTTP 200', function (): void {
    // Verified against a deliberately revoked key: this gateway answers 200 with
    // HSHK_ERR_UA_AUTH. Reading the status alone would call this a success.
    Http::fake(['*' => Http::response(['handshake' => ['id' => 1203, 'label' => 'HSHK_ERR_UA_AUTH'], 'data' => null], 200)]);

    expect(fn () => makeProvider()->send('+233244123456', '123456'))
        ->toThrow(OtpDeliveryFailedException::class);
});

it('distinguishes a credential failure from a message rejection', function (): void {
    Http::fake(['*' => Http::response(['handshake' => ['label' => 'HSHK_ERR_UA_AUTH']], 200)]);

    try {
        makeProvider()->send('+233244123456', '123456');
        $this->fail('expected OtpDeliveryFailedException');
    } catch (OtpDeliveryFailedException $e) {
        // The distinction matters operationally: a credential failure is fixed by
        // rotating a key, a message rejection by fixing the number.
        expect($e->reason())->toBe('rejected_credentials');
    }
});

it('rejects a non-OK handshake label as a delivery failure', function (): void {
    Http::fake(['*' => Http::response(['handshake' => ['label' => 'HSHK_ERR_SOMETHING']], 200)]);

    expect(fn () => makeProvider()->send('+233244123456', '123456'))
        ->toThrow(OtpDeliveryFailedException::class);
});

it('refuses to report success on a 200 carrying no handshake at all', function (): void {
    // Undocumented shape. Assuming success here is the "plausible success" bug
    // this whole class is written to avoid.
    Http::fake(['*' => Http::response(['data' => ['batch' => 'b1']], 200)]);

    expect(fn () => makeProvider()->send('+233244123456', '123456'))
        ->toThrow(OtpDeliveryFailedException::class);
});

it('fails on a gateway 5xx', function (): void {
    Http::fake(['*' => Http::response('upstream exploded', 502)]);

    expect(fn () => makeProvider()->send('+233244123456', '123456'))
        ->toThrow(OtpDeliveryFailedException::class);
});

it('fails without ever calling the gateway when the api key is empty', function (): void {
    Http::fake();

    expect(fn () => makeProvider(apiKey: '')->send('+233244123456', '123456'))
        ->toThrow(OtpDeliveryFailedException::class);

    Http::assertNothingSent();
});

it('never puts the code or the full phone number in the exception message', function (): void {
    Http::fake(['*' => Http::response(['handshake' => ['label' => 'HSHK_ERR_UA_AUTH']], 200)]);

    try {
        makeProvider()->send('+233244123456', '654321');
        $this->fail('expected OtpDeliveryFailedException');
    } catch (OtpDeliveryFailedException $e) {
        // engineering-standards §10 — an OTP or a subscriber number must never
        // ride out on an exception that something upstream might log verbatim.
        expect($e->getMessage())->not->toContain('654321');
        expect($e->getMessage())->not->toContain('233244123456');
    }
});

it('reports its stable name for observability and config matching', function (): void {
    expect(makeProvider()->name())->toBe('smsonlinegh');
});
