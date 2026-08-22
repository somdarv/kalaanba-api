<?php

declare(strict_types=1);

namespace Kalaanba\Modules\PlayerAffiliation\Infrastructure\Media;

use Illuminate\Contracts\Filesystem\Factory as FilesystemFactory;
use Illuminate\Http\UploadedFile;
use Kalaanba\Modules\PlayerAffiliation\Application\PlayerMediaDriver;
use Kalaanba\Modules\PlayerAffiliation\Application\StoredPlayerMedia;
use Kalaanba\Modules\PlayerAffiliation\Domain\PlayerMediaKind;

/**
 * Uploads player media to Cloudflare R2 over its S3-compatible API.
 *
 * Credentials are env-only (`R2_ACCESS_KEY_ID`, `R2_SECRET_ACCESS_KEY`) and
 * never admin config, per engineering-standards §10. The bucket, the public
 * base URL and the driver choice are config, so an environment can be pointed
 * at a different bucket without a deploy.
 *
 * **The public URL is built here, not read back from the disk adapter.** R2
 * buckets are private by default and their S3 endpoint is not the address the
 * browser fetches from — that is either the bucket's `r2.dev` domain or a
 * custom domain, and only the operator knows which. Asking Flysystem for a URL
 * would hand back the private endpoint, which resolves for nobody without a
 * signature. The card would show a broken image and the failure would look like
 * an upload bug rather than a missing setting.
 *
 * **Objects are written public-read.** Player media IS public content (§16:
 * cards are public by default once claimed). The private bucket with signed
 * URLs is the evidence bucket, a different thing under a different rule.
 *
 * Engine doc: docs/engines/player-affiliation/ §7, §16.
 */
final readonly class R2PlayerMediaDriver implements PlayerMediaDriver
{
    public function __construct(
        private FilesystemFactory $filesystem,
        private string $disk,
        private string $publicBaseUrl,
    ) {}

    public function store(UploadedFile $file, string $playerId, PlayerMediaKind $kind): StoredPlayerMedia
    {
        $path = MediaPath::for($file, $playerId, $kind);

        $this->filesystem->disk($this->disk)->putFileAs(
            \dirname($path),
            $file,
            \basename($path),
            'public',
        );

        [$width, $height] = MediaPath::dimensions($file);

        return new StoredPlayerMedia(
            url: rtrim($this->publicBaseUrl, '/').'/'.ltrim($path, '/'),
            kind: $kind,
            width: $width,
            height: $height,
        );
    }
}
