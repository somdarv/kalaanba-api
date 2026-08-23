<?php

declare(strict_types=1);

namespace Kalaanba\Support\Media;

use Illuminate\Http\UploadedFile;

/**
 * Somewhere to put a public image and get back an address for it.
 *
 * Cross-module plumbing under `app/Support/` (engineering-standards §3), and
 * deliberately free of any engine's vocabulary: it takes a path prefix and an
 * owner id, not a "player" or a "club". WHAT an image is remains the owning
 * engine's business, because that is the part with a lifecycle — a headshot
 * survives a transfer, a crest is versioned with the club identity (Club §11),
 * an avatar belongs to the account. Only the byte-pushing is common.
 *
 * **This is the third implementation of the same plumbing.** Identity has
 * `AvatarDriver` and Player & Affiliation has `PlayerMediaDriver`, both
 * predating it, both doing exactly this. They were left alone rather than
 * migrated in the packet that needed a third: moving live media paths is its
 * own change with its own risk. New callers should use this one, and the two
 * older drivers should fold into it when something else touches them.
 *
 * It does NOT validate. The MIME allow-list and the size ceiling belong to the
 * Form Request and run before anything reaches here.
 */
interface ImageStore
{
    /**
     * @param  string  $prefix  Path namespace, e.g. `club-crests`.
     * @param  string  $ownerId  The record the image belongs to.
     */
    public function store(UploadedFile $file, string $prefix, string $ownerId): StoredImage;
}
