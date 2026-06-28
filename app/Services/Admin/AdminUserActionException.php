<?php

declare(strict_types=1);

namespace App\Services\Admin;

use RuntimeException;

/** Carries a stable error code for an admin Users-section action failure. */
final class AdminUserActionException extends RuntimeException
{
    public function __construct(
        public readonly string $errorCode,
        string $message,
        public readonly int $status = 422,
    ) {
        parent::__construct($message);
    }
}
