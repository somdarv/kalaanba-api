<?php

declare(strict_types=1);

namespace Kalaanba\Modules\Season\Domain;

/**
 * The five season phases plus the pre-season + archived terminal states.
 *
 * Engine doc ref: docs/engines/season/Season_Engine_UPDATED.md §2 (calendar),
 * §9 (challenge cutoffs), §13 (locked summary).
 *
 * Internal keys are stable; UI labels live in Admin Config (Constitution §1.4).
 */
enum SeasonPhase: string
{
    case Preseason = 'preseason';
    case Active = 'active';
    case Peak = 'peak';
    case RunIn = 'run_in';
    case Closing = 'closing';
    case Archived = 'archived';

    /** Phases that allow Season RP accrual. Engine doc §2 + §7. */
    public function awardsSeasonRp(): bool
    {
        return match ($this) {
            self::Active, self::Peak, self::RunIn => true,
            self::Preseason, self::Closing, self::Archived => false,
        };
    }
}
