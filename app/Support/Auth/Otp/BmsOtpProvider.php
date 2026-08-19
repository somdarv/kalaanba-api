<?php

declare(strict_types=1);

namespace Kalaanba\Support\Auth\Otp;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Kalaanba\Support\Auth\Otp\Exceptions\OtpDeliveryFailedException;
use Throwable;

/**
 * Delivers OTP codes as SMS through BMS (Bulk Messaging Solutions).
 *
 * ── WHY THE HOST SAYS MNOTIFY ─────────────────────────────────────────────────
 *
 * BMS is the current brand of mNotify, and the API still answers on
 * `api.mnotify.com`. `bms.africa` is the marketing site and does not serve the
 * API. This is not a copy-paste error from another vendor — if you "fix" the
 * base URL to a bms.africa host, every OTP stops.
 *
 * ── THE CONTRACT, VERIFIED LIVE 2026-08-19 ────────────────────────────────────
 *
 * Every line below was observed against the real account, not read off a docs
 * page. The docs site is a JS app that renders nothing to a fetcher, so probing
 * was the only way to establish this — which turned out to matter, because one
 * of the behaviours contradicts what "success" should mean.
 *
 *   POST {base}/api/sms/quick?key=API_KEY
 *   Content-Type: application/json
 *   {"recipient":["233244123456"],"sender":"Kalaanba","message":"...",
 *    "is_schedule":false,"schedule_date":""}
 *
 * Unlike SMSOnlineGH (see {@see SmsOnlineGhOtpProvider}), this gateway uses
 * honest HTTP status codes, which makes it markedly easier to read:
 *
 *   401  {"error":"invalid api key. please make sure your api key is valid and enabled"}
 *   422  {"status":"error","errors":{"sender":["The sender field must not be greater than 11 characters."]}}
 *   422  {"status":"error","errors":{"recipient":["The recipient field is required."]}}
 *   200  {"status":"success","code":"2000","message":"messages sent successfully",
 *         "summary":{...,"total_sent":1,"total_rejected":0,"credit_used":1,"credit_left":3210}}
 *
 * ── THE ONE TRAP ──────────────────────────────────────────────────────────────
 *
 * A MALFORMED RECIPIENT IS REPORTED AS A SUCCESS. Sending to the literal string
 * "not-a-number" returns HTTP 200, `status: success`, `code: 2000` and
 * `total_sent: 1`. The only thing distinguishing it from a real send is
 * `credit_used: 0` — the gateway silently swallowed the number and billed
 * nothing.
 *
 * So `status: success` alone is NOT sufficient, and this class also requires
 * `total_rejected` to be zero. We deliberately do NOT additionally require
 * `credit_used > 0`: an account on a bundled or promotional plan can legitimately
 * bill zero, and refusing those would break delivery for a billing arrangement
 * rather than a real fault. Our numbers are E.164-validated well upstream of
 * here, so the malformed-recipient path should be unreachable in practice; the
 * `total_rejected` check is the belt to that braces.
 *
 * `code` is the STRING "2000", not the integer 2000. Comparing it strictly
 * against an int silently fails every send.
 *
 * ── LOGGING ───────────────────────────────────────────────────────────────────
 *
 * Never log the code, and never log the number unmasked (§10).
 */
final class BmsOtpProvider implements OtpProvider
{
    private const SEND_PATH = '/api/sms/quick';

    private const SUCCESS_CODE = '2000';

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
            throw new OtpDeliveryFailedException('no_api_key');
        }

        try {
            $response = Http::asJson()
                ->timeout($this->timeoutSeconds)
                ->post(
                    rtrim($this->baseUrl, '/').self::SEND_PATH.'?key='.urlencode($this->apiKey),
                    $this->buildPayload($phoneE164, $code),
                );
        } catch (Throwable $e) {
            // Timeout or DNS failure is ambiguous — the message may already have
            // been accepted. Report failure so the user can retry, but never
            // echo the exception to the client.
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
        return 'bms';
    }

    /**
     * The credential rides in the query string, which is the only placement this
     * API accepts — it is not readable from a header or the body.
     *
     * @return array<string, mixed>
     */
    private function buildPayload(string $phoneE164, string $code): array
    {
        return [
            // An array even for one recipient; the field is rejected if scalar.
            // NO LEADING "+" — we store E.164, the gateway takes 233244123456.
            'recipient' => [ltrim($phoneE164, '+')],
            'sender' => $this->senderId,
            'message' => str_replace('{code}', $code, $this->messageTemplate),
            'is_schedule' => false,
            'schedule_date' => '',
        ];
    }

    /**
     * @param  array<string, mixed>  $body
     *
     * @throws OtpDeliveryFailedException
     */
    private function assertAccepted(int $status, array $body, string $phoneE164): void
    {
        if ($status === 401 || $status === 403) {
            $this->logRejection('rejected_credentials', $phoneE164, $status);

            throw new OtpDeliveryFailedException('rejected_credentials');
        }

        if ($status === 422) {
            // Their validation errors name the offending field, which is the
            // single most useful thing in a support thread: "sender" means the
            // sender ID is wrong, "recipient" means the number is.
            $fields = is_array($body['errors'] ?? null)
                ? implode(',', array_keys($body['errors']))
                : 'unknown';

            $this->logRejection('rejected_validation_'.$fields, $phoneE164, $status);

            throw new OtpDeliveryFailedException('rejected_validation_'.$fields);
        }

        if ($status >= 500) {
            $this->logRejection('gateway_error_'.$status, $phoneE164, $status);

            throw new OtpDeliveryFailedException('gateway_error_'.$status);
        }

        if ($status >= 400) {
            $this->logRejection('rejected_'.$status, $phoneE164, $status);

            throw new OtpDeliveryFailedException('rejected_'.$status);
        }

        // Note the string comparison — `code` is "2000", not 2000.
        $code = $body['code'] ?? null;
        $ok = ($body['status'] ?? null) === 'success'
            && (is_string($code) ? $code : (string) $code) === self::SUCCESS_CODE;

        if (! $ok) {
            $this->logRejection('unexpected_body', $phoneE164, $status);

            throw new OtpDeliveryFailedException('unexpected_body');
        }

        // The trap: a 200 + success can still have swallowed the recipient.
        $summary = $body['summary'] ?? null;
        $rejected = is_array($summary) ? ($summary['total_rejected'] ?? 0) : 0;

        if ((int) $rejected > 0) {
            $this->logRejection('recipient_rejected', $phoneE164, $status);

            throw new OtpDeliveryFailedException('recipient_rejected');
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

    /** Last four digits only — never a full subscriber number in a log (§10). */
    private function mask(string $phoneE164): string
    {
        return '***'.mb_substr($phoneE164, -4);
    }
}
