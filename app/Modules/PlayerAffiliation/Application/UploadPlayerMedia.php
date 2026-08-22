<?php

declare(strict_types=1);

namespace Kalaanba\Modules\PlayerAffiliation\Application;

use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Kalaanba\Modules\PlayerAffiliation\Domain\PlayerMediaKind;
use Kalaanba\Modules\PlayerAffiliation\Domain\PlayerRepository;
use Kalaanba\Support\EventBus\OutboxEnvelope;
use Kalaanba\Support\EventBus\OutboxWriter;

/**
 * Use case: a player sets their own photo (engine doc §7).
 *
 * **Ownership is checked here, not in the controller**, for the same reason
 * {@see UpdatePlayerProfile} checks it here: §17 says nobody edits another
 * player's record through this route, not even a club admin, and putting the
 * check in the use case means every future caller inherits it rather than
 * having to remember it.
 *
 * **The upload happens before the transaction, the row write inside it.**
 * Storing bytes in a bucket is a network call to another company, and
 * engineering-standards §13 forbids synchronous outbound HTTP inside a request
 * that also holds a database transaction open. Holding row locks for the
 * length of a 3G upload is how a slow phone blocks the table.
 *
 * That ordering has one consequence worth stating plainly: a stored object
 * whose transaction then fails is an orphan in the bucket. That is the correct
 * trade. The alternative loses the player's upload to protect a few kilobytes,
 * and an orphaned object is cheap, invisible, and collectable later — it is
 * content-addressed, so a retry of the same photo reuses the same key rather
 * than adding a second copy.
 *
 * **Moderation is told, not asked** (Constitution Law 6, Law 10). Player media
 * is public content the moment it lands on a card, so the write raises
 * `player.media_uploaded` through the outbox in the same transaction as the row
 * write. Moderation & Safety decides whether to hold it. This engine never
 * computes a moderation verdict and never waits for one — a photo the owner
 * just picked shows to the owner immediately, and it is the PUBLIC surface's
 * job to consult the verdict before serving it to a stranger.
 */
final class UploadPlayerMedia
{
    public function __construct(
        private readonly PlayerRepository $repository,
        private readonly PlayerMediaDriver $driver,
        private readonly OutboxWriter $outbox,
    ) {}

    /**
     * @throws PlayerNotFound when no live player carries this id
     * @throws PlayerNotYours when the actor does not own it
     */
    public function execute(
        string $playerId,
        string $actorUserId,
        UploadedFile $file,
        PlayerMediaKind $kind,
    ): StoredPlayerMedia {
        $player = $this->repository->findById($playerId);
        if ($player === null) {
            throw new PlayerNotFound($playerId);
        }
        if ($player->userId !== $actorUserId) {
            throw new PlayerNotYours($playerId);
        }

        $stored = $this->driver->store($file, $playerId, $kind);
        $now = new DateTimeImmutable('now', new DateTimeZone('UTC'));

        DB::transaction(function () use ($player, $stored, $kind, $now): void {
            // The headshot is the only kind with a column to land in today, and
            // writing it here rather than making the client PATCH afterwards is
            // what stops a dropped connection leaving a stored photo that no
            // card points at.
            if ($kind->updatesHeadshotUrl()) {
                $this->repository->update($player->withHeadshotUrl($stored->url));
            }

            $this->outbox->write(new OutboxEnvelope(
                eventId: (string) Str::uuid(),
                eventName: 'player.media_uploaded',
                schemaVersion: 1,
                occurredAt: $now,
                actorId: $player->userId,
                actorRole: 'player',
                source: 'player-affiliation',
                payload: [
                    'player_id' => $player->id,
                    'user_id' => $player->userId,
                    'kind' => $kind->value,
                    'url' => $stored->url,
                    'width' => $stored->width,
                    'height' => $stored->height,
                    'uploaded_at' => $now->format(DATE_ATOM),
                ],
            ));
        });

        return $stored;
    }
}
