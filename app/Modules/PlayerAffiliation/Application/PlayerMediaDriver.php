<?php

declare(strict_types=1);

namespace Kalaanba\Modules\PlayerAffiliation\Application;

use Illuminate\Http\UploadedFile;
use Kalaanba\Modules\PlayerAffiliation\Domain\PlayerMediaKind;

/**
 * Driver contract for persisting player image uploads (engine doc §7).
 *
 * Implementations live in PlayerAffiliation\Infrastructure\Media:
 *  - LocalPlayerMediaDriver — storage/app/public (dev, test, CI default).
 *  - R2PlayerMediaDriver    — Cloudflare R2 over the S3 API (deployed).
 *
 * Selection is config-driven through `player.media.driver`; see
 * {@see \Kalaanba\Modules\PlayerAffiliation\Infrastructure\Media\PlayerMediaDriverFactory}.
 *
 * **Deliberately separate from Identity's AvatarDriver, and not a subclass of
 * it.** §7 splits the two: `users.avatar_url` is the person behind the account,
 * `players.headshot_url` is football media that appears on team sheets and
 * lineups. One player profile can be claimed, transferred or archived without
 * touching the account picture, and a ghost player has media before it has an
 * account at all (§5). Sharing one driver would tie two lifecycles that the
 * engine doc keeps apart, and would put a Player concern inside the Identity
 * module in breach of Constitution Law 1.
 */
interface PlayerMediaDriver
{
    /**
     * Persist an upload and return its resolvable address.
     *
     * The implementation owns content-addressing (hash the bytes, so the same
     * photo uploaded twice costs one object) and any provider-specific
     * defaults. It does NOT validate: the MIME allow-list and size ceiling are
     * the Form Request's job, applied before anything reaches here.
     */
    public function store(UploadedFile $file, string $playerId, PlayerMediaKind $kind): StoredPlayerMedia;
}
