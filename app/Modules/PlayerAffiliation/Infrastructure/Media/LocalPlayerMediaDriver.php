<?php

declare(strict_types=1);

namespace Kalaanba\Modules\PlayerAffiliation\Infrastructure\Media;

use Illuminate\Contracts\Filesystem\Factory as FilesystemFactory;
use Illuminate\Http\UploadedFile;
use Kalaanba\Modules\PlayerAffiliation\Application\PlayerMediaDriver;
use Kalaanba\Modules\PlayerAffiliation\Application\StoredPlayerMedia;
use Kalaanba\Modules\PlayerAffiliation\Domain\PlayerMediaKind;

/**
 * Writes player media to a local disk under
 * `player-media/{kind}/{playerId}/{contentHash}.{ext}`.
 *
 * The default in dev, test and CI, and the reason the feature works with no
 * cloud credentials configured at all.
 *
 * **Content-addressed, and that is not just tidiness.** Hashing the bytes means
 * a player who re-uploads the same photo overwrites one object instead of
 * growing the bucket, and it makes the path unguessable, so nobody enumerates
 * `player-media/headshot/{id}/1.jpg` upward through the country's faces.
 *
 * Engine doc: docs/engines/player-affiliation/ §7.
 */
final readonly class LocalPlayerMediaDriver implements PlayerMediaDriver
{
    public function __construct(
        private FilesystemFactory $filesystem,
        private string $disk = 'public',
    ) {}

    public function store(UploadedFile $file, string $playerId, PlayerMediaKind $kind): StoredPlayerMedia
    {
        $path = MediaPath::for($file, $playerId, $kind);
        $disk = $this->filesystem->disk($this->disk);

        $disk->putFileAs(\dirname($path), $file, \basename($path), 'public');

        [$width, $height] = MediaPath::dimensions($file);

        return new StoredPlayerMedia(
            url: $disk->url($path),
            kind: $kind,
            width: $width,
            height: $height,
        );
    }
}
