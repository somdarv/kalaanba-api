<?php

declare(strict_types=1);

namespace Kalaanba\Modules\NotificationDistribution\Domain;

/**
 * Cursor used by the inbox listing endpoint. Encodes the last row's
 * (created_at, id) pair so the next page resumes deterministically even
 * when new rows arrive at the head of the stream.
 */
final readonly class InboxCursor
{
    public function __construct(
        public string $createdAtIso,
        public string $id,
    ) {
    }

    public function encode(): string
    {
        return base64_encode($this->createdAtIso.'|'.$this->id);
    }

    public static function decode(string $cursor): ?self
    {
        $decoded = base64_decode($cursor, true);
        if ($decoded === false) {
            return null;
        }
        $parts = explode('|', $decoded, 2);
        if (count($parts) !== 2 || $parts[0] === '' || $parts[1] === '') {
            return null;
        }

        return new self($parts[0], $parts[1]);
    }
}
