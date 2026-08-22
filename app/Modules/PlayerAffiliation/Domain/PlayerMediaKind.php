<?php

declare(strict_types=1);

namespace Kalaanba\Modules\PlayerAffiliation\Domain;

/**
 * The role an uploaded player image plays (engine doc §7).
 *
 * §7 is explicit that "a single photo cannot serve every design surface" and
 * that "the data model should support all types from the start", which is why
 * all three exist now even though V1 only requires the headshot. A card that
 * later gains a standing-figure slot must not need a migration to fill it.
 *
 * Stable internal keys, never display strings (Constitution Law 4).
 */
enum PlayerMediaKind: string
{
    /** Tight face crop. Small avatars, lineups, team sheets, the card photo. */
    case Headshot = 'headshot';

    /** Waist-up. The card's portrait slot and the profile hero. */
    case HalfBody = 'half_body';

    /** Standing. Special cards, spotlights, posters. */
    case FullBody = 'full_body';

    /**
     * Whether storing this image also updates `players.headshot_url`.
     *
     * Only the headshot does. The other two have no column to land in yet and
     * writing them into the headshot field would put a waist-up shot into every
     * team sheet in the country.
     */
    public function updatesHeadshotUrl(): bool
    {
        return $this === self::Headshot;
    }

    /** @return list<string> */
    public static function values(): array
    {
        return array_map(static fn (self $kind): string => $kind->value, self::cases());
    }
}
