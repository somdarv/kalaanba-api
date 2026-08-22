<?php

declare(strict_types=1);

namespace Kalaanba\Modules\PlayerAffiliation\Infrastructure\Media;

use Illuminate\Http\UploadedFile;
use Kalaanba\Modules\PlayerAffiliation\Domain\PlayerMediaKind;

/**
 * Where a player image lives, and how big it is.
 *
 * Shared by both drivers so a photo keeps the same key whichever one stored it.
 * If the two derived paths separately, moving an environment from local to R2
 * would orphan every object already written.
 */
final class MediaPath
{
    /**
     * `player-media/{kind}/{playerId}/{sha256}.{ext}`.
     *
     * Kind first, so a bucket lifecycle rule or a moderation sweep can target
     * one class of image without walking every player.
     */
    public static function for(UploadedFile $file, string $playerId, PlayerMediaKind $kind): string
    {
        $realPath = (string) $file->getRealPath();
        $hash = hash_file('sha256', $realPath);

        return sprintf(
            'player-media/%s/%s/%s.%s',
            $kind->value,
            $playerId,
            $hash === false ? bin2hex(random_bytes(16)) : $hash,
            self::extension($file),
        );
    }

    /**
     * Pixel dimensions, or nulls.
     *
     * Best-effort on purpose. `getimagesize` is a read of the file the Form
     * Request has already accepted, and a decoder that cannot read it is not a
     * reason to refuse an upload that passed validation — the contract types
     * width and height as optional for exactly this case.
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
     * Extension from the SERVER's sniff first, the client's name second.
     *
     * `guessExtension()` reads the file; `getClientOriginalExtension()` reads a
     * string the browser sent. Trusting the client string first is how a
     * `.php` lands in a public bucket, so it is only a fallback for the case
     * where the sniff yields nothing.
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
