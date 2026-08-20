<?php

declare(strict_types=1);

namespace Kalaanba\Modules\PlayerAffiliation\Application;

use RuntimeException;

/**
 * Raised when the acting user lacks the authority to decide a join request
 * (not a club Owner/Admin — engine doc §11). Mapped to HTTP 403.
 */
final class AffiliationDenied extends RuntimeException {}
