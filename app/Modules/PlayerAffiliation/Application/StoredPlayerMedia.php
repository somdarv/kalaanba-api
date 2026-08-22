<?php

declare(strict_types=1);

namespace Kalaanba\Modules\PlayerAffiliation\Application;

use Kalaanba\Modules\PlayerAffiliation\Domain\PlayerMediaKind;

/**
 * What a {@see PlayerMediaDriver} hands back after storing an image.
 *
 * `url` is a plain, resolvable address, never a signed one. Player media is
 * public content the moment it lands on a card, which puts it in a different
 * class from the evidence bucket that engineering-standards §11 reserves signed
 * URLs for. Signing this would also mean the URL expires while the player row
 * still points at it.
 */
final readonly class StoredPlayerMedia
{
    public function __construct(
        public string $url,
        public PlayerMediaKind $kind,
        public ?int $width = null,
        public ?int $height = null,
    ) {}
}
