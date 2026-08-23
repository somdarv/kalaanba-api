<?php

declare(strict_types=1);

namespace Kalaanba\Support\Media;

use Illuminate\Http\UploadedFile;

/**
 * Where an image lives, and how big it is.
 *
 * Shared by every {@see ImageStore} implementation so an object keeps the same
 * key whichever one wrote it. If the local and remote stores derived paths
 * separately, moving an environment from one to the other would orphan
 * everything already written.
 */
final class ImagePath
{
    /**
     * `{prefix}/{ownerId}/{sha256}.{ext}`.
     *
     * Content-addressed, so the same image uploaded twice costs one object and
     * a retry after a dropped connection does not leave a duplicate.
     */
    public static function for(UploadedFile $file, string $prefix, string $ownerId): string
    {
        $hash = hash_file('sha256', (string) $file->getRealPath());

        return sprintf(
            '%s/%s/%s.%s',
            trim($prefix, '/'),
            $ownerId,
            $hash === false ? bin2hex(random_bytes(16)) : $hash,
            self::extension($file),
        );
    }

    /**
     * Pixel dimensions, or nulls.
     *
     * Best-effort on purpose: a decoder that cannot read a file the Form
     * Request already accepted is not a reason to refuse the upload.
     *
     * @return array{0: int|null, 1: int|null}
     */
    public static function dimensions(UploadedFile $file): array
    {
        $size = @getimagesize((string) $file->getRealPath());

        if ($size === false) {
            return [null, null];
        }

        return [
            $size[0] > 0 ? $size[0] : null,
            $size[1] > 0 ? $size[1] : null,
        ];
    }

    /**
     * Extension from the SERVER's sniff first, the client's filename second.
     *
     * `guessExtension()` reads the bytes; `getClientOriginalExtension()` reads
     * a string the browser sent. Trusting the client string first is how a
     * `.php` lands in a public bucket.
     */
    private static function extension(UploadedFile $file): string
    {
        $guessed = $file->guessExtension();
        if (is_string($guessed) && $guessed !== '') {
            return strtolower($guessed);
        }

        $client = $file->getClientOriginalExtension();

        return $client !== '' ? strtolower($client) : 'bin';
    }
}
