<?php

declare(strict_types=1);

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Kalaanba\Support\Auth\Otp\BmsOtpProvider;
use Kalaanba\Support\Auth\Otp\Exceptions\OtpDeliveryFailedException;
use Tests\TestCase;

/*
 * Bound per-file: the driver talks through the Http and Log facades, which need
 * a booted container for Http::fake() to have anything to swap.
 *
 * Every response body below was captured from the LIVE gateway on 2026-08-19,
 * not written from their documentation. That distinction is the whole point —
 * a faked gateway agrees with whatever you send it, so a test built from an
 * imagined contract proves only that we are consistent with ourselves.
 */
uses(TestCase::class);

function makeBmsProvider(string $apiKey = 'test-key'): BmsOtpProvider
{
    return new BmsOtpProvider(
        apiKey: $apiKey,
        senderId: 'Kalaanba',
        baseUrl: 'https://api.mnotify.com',
        timeoutSeconds: 10,
        messageTemplate: 'Your Kalaanba code is {code}. It expires in 5 minutes.',
    );
}

/** The real shape of an accepted send. */
function bmsSuccessBody(int $rejected = 0, int $creditUsed = 1): array
{
    return [
        'status' => 'success',
        // A STRING, not an int. Comparing strictly against 2000 fails every send.
        'code' => '2000',
        'message' => 'messages sent successfully',
        'summary' => [
            'message_id' => '20260819233557792785V2',
            'type' => 'API QUICK SMS',
            'total_sent' => 1,
            'contacts' => 1,
            'total_rejected' => $rejected,
            'credit_used' => $creditUsed,
            'credit_left' => 3210,
        ],
    ];
}

it('posts JSON to the quick-sms path with the key in the query string', function (): void {
    Http::fake(['*' => Http::response(bmsSuccessBody(), 200)]);

    makeBmsProvider()->send('+233244123456', '123456');

    Http::assertSent(function (Request $request): bool {
        expect($request->url())->toContain('https://api.mnotify.com/api/sms/quick');
        // The credential is only readable from the query string on this API.
        expect($request->url())->toContain('key=test-key');
        expect($request->hasHeader('Content-Type', 'application/json'))->toBeTrue();

        return true;
    });
});

it('sends the recipient as an array with no leading plus', function (): void {
    Http::fake(['*' => Http::response(bmsSuccessBody(), 200)]);

    makeBmsProvider()->send('+233244123456', '123456');

    Http::assertSent(function (Request $r): bool {
        // Scalar recipients are rejected by the gateway; it must be an array.
        expect($r['recipient'])->toBe(['233244123456']);
        expect($r['sender'])->toBe('Kalaanba');
        expect($r['is_schedule'])->toBeFalse();

        return true;
    });
});

it('substitutes the code into the configured template', function (): void {
    Http::fake(['*' => Http::response(bmsSuccessBody(), 200)]);

    makeBmsProvider()->send('+233244123456', '987654');

    Http::assertSent(fn (Request $r): bool
        => $r['message'] === 'Your Kalaanba code is 987654. It expires in 5 minutes.');
});

it('accepts the string code 2000 rather than the integer', function (): void {
    // Guards the exact mistake that would break every send silently in review.
    Http::fake(['*' => Http::response(bmsSuccessBody(), 200)]);

    makeBmsProvider()->send('+233244123456', '123456');

    expect(true)->toBeTrue(); // reaching here without throwing is the assertion
});

it('fails on an invalid api key, which this gateway reports as a real 401', function (): void {
    Http::fake(['*' => Http::response(
        ['error' => 'invalid api key. please make sure your api key is valid and enabled'],
        401,
    )]);

    try {
        makeBmsProvider()->send('+233244123456', '123456');
        $this->fail('expected OtpDeliveryFailedException');
    } catch (OtpDeliveryFailedException $e) {
        expect($e->reason())->toBe('rejected_credentials');
    }
});

it('names the offending field when the gateway returns a 422', function (): void {
    // Observed live: an over-long sender ID produces exactly this.
    Http::fake(['*' => Http::response([
        'status' => 'error',
        'errors' => ['sender' => ['The sender field must not be greater than 11 characters.']],
    ], 422)]);

    try {
        makeBmsProvider()->send('+233244123456', '123456');
        $this->fail('expected OtpDeliveryFailedException');
    } catch (OtpDeliveryFailedException $e) {
        // Knowing it was "sender" and not "recipient" is the difference between
        // fixing a config key and chasing a phone number.
        expect($e->reason())->toBe('rejected_validation_sender');
    }
});

it('refuses a 200 that reports rejected recipients', function (): void {
    // THE TRAP. This gateway answers 200 + status:success for a malformed
    // recipient. total_rejected is what distinguishes it from a real send.
    Http::fake(['*' => Http::response(bmsSuccessBody(rejected: 1, creditUsed: 0), 200)]);

    try {
        makeBmsProvider()->send('+233244123456', '123456');
        $this->fail('expected OtpDeliveryFailedException');
    } catch (OtpDeliveryFailedException $e) {
        expect($e->reason())->toBe('recipient_rejected');
    }
});

it('still accepts a zero-credit send, because bundled plans legitimately bill nothing', function (): void {
    // Deliberately NOT treated as failure: refusing this would break delivery
    // for a billing arrangement rather than a real fault.
    Http::fake(['*' => Http::response(bmsSuccessBody(rejected: 0, creditUsed: 0), 200)]);

    makeBmsProvider()->send('+233244123456', '123456');

    Http::assertSentCount(1);
});

it('refuses a 200 whose body is not the documented success shape', function (): void {
    Http::fake(['*' => Http::response(['status' => 'error', 'message' => 'something else'], 200)]);

    expect(fn () => makeBmsProvider()->send('+233244123456', '123456'))
        ->toThrow(OtpDeliveryFailedException::class);
});

it('fails on a gateway 5xx', function (): void {
    Http::fake(['*' => Http::response('upstream exploded', 503)]);

    expect(fn () => makeBmsProvider()->send('+233244123456', '123456'))
        ->toThrow(OtpDeliveryFailedException::class);
});

it('fails without calling the gateway when the api key is empty', function (): void {
    Http::fake();

    expect(fn () => makeBmsProvider(apiKey: '')->send('+233244123456', '123456'))
        ->toThrow(OtpDeliveryFailedException::class);

    Http::assertNothingSent();
});

it('never puts the code or the full phone number in the exception message', function (): void {
    Http::fake(['*' => Http::response(['error' => 'invalid api key'], 401)]);

    try {
        makeBmsProvider()->send('+233244123456', '654321');
        $this->fail('expected OtpDeliveryFailedException');
    } catch (OtpDeliveryFailedException $e) {
        expect($e->getMessage())->not->toContain('654321');
        expect($e->getMessage())->not->toContain('233244123456');
    }
});

it('reports its stable name for config matching and observability', function (): void {
    expect(makeBmsProvider()->name())->toBe('bms');
});
