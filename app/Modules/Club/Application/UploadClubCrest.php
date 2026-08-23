<?php

declare(strict_types=1);

namespace Kalaanba\Modules\Club\Application;

use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Kalaanba\Modules\Club\Domain\ClubReader;
use Kalaanba\Modules\Club\Domain\ClubRepository;
use Kalaanba\Support\EventBus\OutboxEnvelope;
use Kalaanba\Support\EventBus\OutboxWriter;
use Kalaanba\Support\Media\ImageStore;
use Kalaanba\Support\Media\StoredImage;

/**
 * Use case: a club admin sets the club crest (engine doc §5 step 6, §11).
 *
 * **Authority is checked here, not in the controller**, so every future caller
 * inherits it rather than having to remember: §7 puts club identity changes at
 * Owner/Admin level, and a crest is the most visible piece of identity a club
 * has.
 *
 * The club is looked up INCLUDING unverified ones. A professional claim sits at
 * `verification_state = pending` while an admin checks it, and its Owner must
 * still be able to finish setting it up; the crest is not public until the club
 * is (ADR-0017).
 *
 * **The upload happens before the transaction, the row write inside it.**
 * Pushing bytes to a bucket is a network call to another company, and
 * engineering-standards §13 forbids synchronous outbound HTTP while a database
 * transaction is open. Holding a row lock for the length of a 3G upload is how
 * one slow phone blocks the table.
 *
 * The consequence, stated plainly: an object whose transaction then fails is an
 * orphan in the bucket. That is the right trade. The alternative loses the
 * upload to protect a few kilobytes, and the object is content-addressed, so a
 * retry reuses the same key rather than adding a second copy.
 *
 * **Moderation is told, not asked** (Law 6, Law 10). A crest is public content
 * the moment the club is, so the write raises `club.crest_updated` through the
 * outbox in the same transaction. Moderation & Safety decides whether to hold
 * it; this engine never computes that verdict and never waits for one.
 */
final class UploadClubCrest
{
    /** Path namespace in the bucket. Lets a lifecycle rule target crests alone. */
    private const PREFIX = 'club-crests';

    public function __construct(
        private readonly ClubReader $reader,
        private readonly ClubRepository $repository,
        private readonly ImageStore $images,
        private readonly OutboxWriter $outbox,
    ) {}

    /**
     * @throws ClubNotFound when no live club carries this id
     * @throws ClubNotYours when the actor does not administer it
     */
    public function execute(
        string $clubId,
        string $actorUserId,
        UploadedFile $file,
    ): StoredImage {
        $club = $this->reader->findByIdIncludingUnverified($clubId);

        if ($club === null) {
            throw new ClubNotFound($clubId);
        }

        if (! $this->reader->userIsClubAdmin($clubId, $actorUserId)) {
            throw new ClubNotYours($clubId);
        }

        $stored = $this->images->store($file, self::PREFIX, $clubId);
        $now = new DateTimeImmutable('now', new DateTimeZone('UTC'));

        DB::transaction(function () use ($clubId, $stored, $actorUserId, $now): void {
            $this->repository->updateCrestUrl($clubId, $stored->url, $now);

            $this->outbox->write(new OutboxEnvelope(
                eventId: (string) Str::uuid(),
                eventName: 'club.crest_updated',
                schemaVersion: 1,
                occurredAt: $now,
                actorId: $actorUserId,
                actorRole: 'user',
                source: 'club',
                payload: [
                    'club_id' => $clubId,
                    'crest_url' => $stored->url,
                    'updated_at' => $now->format(DATE_ATOM),
                ],
            ));
        });

        return $stored;
    }
}
