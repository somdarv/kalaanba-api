<?php

declare(strict_types=1);

namespace Kalaanba\Support\Media;

use Illuminate\Contracts\Filesystem\Factory as FilesystemFactory;
use Illuminate\Http\UploadedFile;

/**
 * Writes to Cloudflare R2 over its S3-compatible API.
 *
 * Credentials are env-only and never admin config (engineering-standards §10).
 * The bucket, the public base URL and the store choice are config, so an
 * environment can be pointed elsewhere without a deploy.
 *
 * **The public URL is built here, not read back from the disk adapter.** R2
 * buckets are private by default and their S3 endpoint is not the address a
 * browser fetches from; that is either the bucket's `r2.dev` domain or a custom
 * one, and only the operator knows which. Asking Flysystem would hand back the
 * private endpoint, which resolves for nobody without a signature, and the
 * resulting broken image would look like an upload bug rather than a missing
 * setting.
 *
 * **Objects are written public-read**, because everything routed through this
 * store is public content. The private bucket with signed URLs is the evidence
 * bucket: a different thing under a different rule (§11).
 */
final readonly class R2ImageStore implements ImageStore
{
    public function __construct(
        private FilesystemFactory $filesystem,
        private string $disk,
        private string $publicBaseUrl,
    ) {}

    public function store(UploadedFile $file, string $prefix, string $ownerId): StoredImage
    {
        $path = ImagePath::for($file, $prefix, $ownerId);

        $this->filesystem->disk($this->disk)->putFileAs(
            \dirname($path),
            $file,
            \basename($path),
            'public',
        );

        [$width, $height] = ImagePath::dimensions($file);

        return new StoredImage(
            url: rtrim($this->publicBaseUrl, '/').'/'.ltrim($path, '/'),
            width: $width,
            height: $height,
        );
    }
}
