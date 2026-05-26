<?php

declare(strict_types=1);

namespace Kalaanba\Modules\Zone\Domain;

/**
 * Lifecycle of a user-submitted Area suggestion.
 *
 * Engine doc §5; Constitution §1.5 (audited), §1.13 (archive don't delete —
 * suggestion rows are preserved with terminal status).
 */
enum AreaSuggestionStatus: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';

    public function isTerminal(): bool
    {
        return $this !== self::Pending;
    }
}
