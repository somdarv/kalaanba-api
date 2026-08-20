<?php

declare(strict_types=1);

namespace Kalaanba\Modules\PlayerAffiliation\Domain;

/**
 * Player availability states (engine doc §12). Stable internal keys —
 * human labels ("Ready to go" etc.) are configurable and resolved elsewhere
 * (Constitution §1.4).
 */
enum PlayerAvailability: string
{
    case Available = 'available';
    case Limited = 'limited';
    case Unavailable = 'unavailable';
    case Unknown = 'unknown';
}
