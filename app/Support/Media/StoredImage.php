<?php

declare(strict_types=1);

namespace Kalaanba\Support\Media;

/**
 * What an {@see ImageStore} hands back after writing bytes.
 *
 * `url` is a plain, resolvable address, never a signed one. Everything stored
 * through this port is public content — a face on a card, a crest on a team
 * sheet — which puts it in a different class from the evidence bucket that
 * engineering-standards §11 reserves signed URLs for. Signing this would also
 * mean the URL expires while the row pointing at it does not.
 */
final readonly class StoredImage
{
    public function __construct(
        public string $url,
        public ?int $width = null,
        public ?int $height = null,
    ) {}
}
