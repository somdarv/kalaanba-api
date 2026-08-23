<?php

declare(strict_types=1);

namespace Kalaanba\Support\Media;

use Illuminate\Contracts\Filesystem\Factory as FilesystemFactory;
use Illuminate\Http\UploadedFile;

/**
 * Writes to a local disk and serves through the app's own URL. The default in
 * dev, test and CI, so nothing needs a bucket to run the suite.
 */
final readonly class LocalImageStore implements ImageStore
{
    public function __construct(
        private FilesystemFactory $filesystem,
        private string $disk,
    ) {}

    public function store(UploadedFile $file, string $prefix, string $ownerId): StoredImage
    {
        $path = ImagePath::for($file, $prefix, $ownerId);
        $disk = $this->filesystem->disk($this->disk);

        $disk->putFileAs(\dirname($path), $file, \basename($path), 'public');

        [$width, $height] = ImagePath::dimensions($file);

        return new StoredImage(
            url: $disk->url($path),
            width: $width,
            height: $height,
        );
    }
}
