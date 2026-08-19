<?php

declare(strict_types=1);

namespace Kalaanba\Support\Auth\Otp;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Kalaanba\Support\Auth\Otp\Exceptions\OtpDeliveryFailedException;
use Throwable;

/**
 * Delivers OTP codes as SMS through SMSOnlineGH.
 *
 * ── THE WIRE FORMAT, AND THE THREE TRAPS IN IT ────────────────────────────────
 *
 * The shape below is not composed from their documentation. It was verified
 * against the live reseller account on 2026-08-18 while building the same
 * integration for a sibling product, and three things about it are counter-
 * intuitive enough that getting them wrong produces a system that looks like it
 * works:
 *
 *   1. FORM ENCODING, NEVER JSON. The `/v5/` API parses only
 *      `application/x-www-form-urlencoded`. A JSON body is not rejected with a
 *      helpful error, it simply cannot be parsed. Hence `Http::asForm()`.
 *
 *   2. A 200 IS NOT AN ACCEPTANCE. The submission verdict lives in
 *      `handshake.label`, and only `HSHK_OK` means accepted. Reading the HTTP
 *      status alone reports every rejected message as sent.
 *
 *   3. AN AUTH FAILURE ARRIVES AS HTTP 200, as
 *      `{"handshake":{"label":"HSHK_ERR_UA_AUTH"},"data":null}` — verified with a
 *      deliberately revoked key. So a 401/403 branch is dead code on this
 *      gateway, and a bad key must be recognised from the label, or every code
 *      issued during a key rotation fails as though the phone number were wrong.
 *
 *   POST {base}/v5/message/sms/send
 *   Content-Type: application/x-www-form-urlencoded
 *   key=API_KEY&text=Your+code+is+123456&type=0&sender=Kalaanba&to=233244123456
 *
 * ── THE SENDER ID FAILS INVISIBLY ─────────────────────────────────────────────
 *
 * The gateway validates nothing about `sender` at submit time. Sending under an
 * unregistered sender returns `HSHK_OK` with a batch reference exactly as an
 * approved one does. No return value from this class can tell you the sender is
 * wrong — only the text arriving on a handset can. If codes are reported sent
 * and nobody receives them, the sender ID is the first thing to check, not the
 * last. It is an admin config key (`auth.otp.sms.sender_id`) precisely so it can
 * be corrected without a deploy.
 *
 * Alphanumeric sender IDs cap at 11 characters.
 *
 * ── WHY THIS IS NOT QUEUED ────────────────────────────────────────────────────
 *
 * engineering-standards §13 says no synchronous outbound HTTP in a request
 * lifecycle. This is a deliberate, documented exception (ADR-0008), for a reason
 * that outranks it: a queued job serialises its payload into the `jobs` table,
 * which would write the plaintext OTP and the subscriber's phone number to the
 * database and leave them there for the life of the row. Section 10 forbids
 * exactly that. The code is a short-lived secret and must not come to rest.
 *
 * The cost is bounded by `smsonlinegh.timeout_seconds`, and the OTP endpoint is
 * already rate-limited (`throttle:otp`), so this cannot become an amplification
 * vector.
 *
 * ── LOGGING ───────────────────────────────────────────────────────────────────
 *
 * Never log the code, and never log the number unmasked (section 10). Failures
 * log a machine token and a masked phone, which is enough to correlate with a
 * support report and nothing more.
 */
final class SmsOnlineGhOtpProvider implements OtpProvider
{
    /**
     * Handshake labels that mean "our credentials are wrong", never "this
     * message is wrong". Listed explicitly rather than matched on `AUTH` as a
     * substring: a sender-approval rejection would also contain that string and
     * has a completely different remedy.
     */
    private const CREDENTIAL_FAILURES = ['HSHK_ERR_UA_AUTH'];

    private const SEND_PATH = '/v5/message/sms/send';

    public function __construct(
        private readonly string $apiKey,
        private readonly string $senderId,
        private readonly string $baseUrl,
        private readonly int $timeoutSeconds,
        /** Message wording. `{code}` is substituted; anything else passes through. */
        private readonly string $messageTemplate,
    ) {}

    /**
     * @throws OtpDeliveryFailedException when the code did not reach the gateway.
     */
    public function send(string $phoneE164, string $code): void
    {
        if ($this->apiKey === '') {
            // Belt and braces. AppServiceProvider refuses to select this driver
            // without a key, but a provider that would happily POST an empty
            // credential is one bad merge away from doing it.
            throw new OtpDeliveryFailedException('no_api_key');
        }

        try {
            $response = Http::asForm()
                ->timeout($this->timeoutSeconds)
                ->post(
                    rtrim($this->baseUrl, '/').self::SEND_PATH,
                    $this->buildPayload($phoneE164, $code),
                );
        } catch (Throwable $e) {
            // A timeout or DNS failure is genuinely ambiguous: the message may
            // already have been accepted. Report failure so the user can retry,
            // but never echo the exception message to the client.
            Log::warning('otp.sms.transport_failed', [
                'provider' => $this->name(),
                'phone' => $this->mask($phoneE164),
                'error' => $e->getMessage(),
            ]);

            throw new OtpDeliveryFailedException('transport_error');
        }

        $this->assertAccepted($response->status(), $response->json() ?? [], $phoneE164);
    }

    public function name(): string
    {
        return 'smsonlinegh';
    }

    /**
     * Five flat parameters, exactly as their canonical example sends them.
     * Nothing here is nested — a nested body is unparseable by this API.
     *
     * @return array<string, string|int>
     */
    private function buildPayload(string $phoneE164, string $code): array
    {
        return [
            // The credential travels as a body parameter. Their docs also allow a
            // query param or an `Authorization: key <KEY>` header; the body is
            // what their own example uses, so it is the path least likely to be
            // subtly wrong at a seam nothing exercises.
            'key' => $this->apiKey,
            'text' => str_replace('{code}', $code, $this->messageTemplate),
            // 0 = plain text. Other types are flash and unicode, which we never send.
            'type' => 0,
            'sender' => $this->senderId,
            // NO LEADING "+". We store E.164; their examples take `233244123456`.
            // This is the one place the two conventions meet.
            'to' => ltrim($phoneE164, '+'),
        ];
    }

    /**
     * Turn their response into "handed to the gateway" or an exception.
     *
     * @param  array<string, mixed>  $body
     *
     * @throws OtpDeliveryFailedException
     */
    private function assertAccepted(int $status, array $body, string $phoneE164): void
    {
        $handshake = $body['handshake'] ?? null;
        $label = is_array($handshake) ? ($handshake['label'] ?? null) : null;

        // Trap 3: the auth failure is an HTTP 200, so the label is checked before
        // the status. Checking status first would classify a revoked key as a
        // generic success.
        if (is_string($label) && $label !== 'HSHK_OK') {
            $reason = in_array($label, self::CREDENTIAL_FAILURES, true)
                ? 'rejected_credentials'
                : 'rejected_'.$label;

            $this->logRejection($reason, $phoneE164, $status);

            throw new OtpDeliveryFailedException($reason);
        }

        if ($status >= 500) {
            $this->logRejection('gateway_error_'.$status, $phoneE164, $status);

            throw new OtpDeliveryFailedException('gateway_error_'.$status);
        }

        if ($status >= 400) {
            $this->logRejection('rejected_'.$status, $phoneE164, $status);

            throw new OtpDeliveryFailedException('rejected_'.$status);
        }

        // Trap 2 again: a 2xx carrying no handshake label at all is not a
        // documented response. Treat it as failure rather than assume success —
        // the whole point of reading the label is refusing plausible successes.
        if (! is_string($label)) {
            $this->logRejection('missing_handshake', $phoneE164, $status);

            throw new OtpDeliveryFailedException('missing_handshake');
        }
    }

    private function logRejection(string $reason, string $phoneE164, int $status): void
    {
        Log::warning('otp.sms.rejected', [
            'provider' => $this->name(),
            'phone' => $this->mask($phoneE164),
            'reason' => $reason,
            'http_status' => $status,
        ]);
    }

    /** Last four digits only — never a full subscriber number in a log (section 10). */
    private function mask(string $phoneE164): string
    {
        return '***'.mb_substr($phoneE164, -4);
    }
}
