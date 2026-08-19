<?php

declare(strict_types=1);

namespace Kalaanba\Support\Auth\Otp\Exceptions;

/**
 * The OTP was generated and stored, but the provider could not hand it to the
 * user's handset.
 *
 * Raised rather than swallowed on purpose. A user who is told "code sent" and
 * never receives one has no move available except to retry into the same
 * failure; surfacing it lets the client say something true and lets the caller
 * fall back to the email channel.
 *
 * Carries no gateway detail into its message — the API boundary renders
 * {@see errorCode()} and nothing else, so a gateway string can never leak a
 * credential or a phone number into a client response (engineering-standards §10).
 */
final class OtpDeliveryFailedException extends OtpException
{
    /**
     * @param  string  $reason  Short machine token for logs only (e.g. `transport_error`,
     *                          `rejected_credentials`). Never rendered to the client.
     */
    public function __construct(private readonly string $reason)
    {
        parent::__construct('OTP delivery failed: '.$reason);
    }

    public function reason(): string
    {
        return $this->reason;
    }

    public function errorCode(): string
    {
        return 'auth.otp_delivery_failed';
    }
}
