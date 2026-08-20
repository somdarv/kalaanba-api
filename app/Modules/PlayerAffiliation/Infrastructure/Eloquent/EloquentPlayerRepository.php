<?php

declare(strict_types=1);

namespace Kalaanba\Modules\PlayerAffiliation\Infrastructure\Eloquent;

use DateTimeImmutable;
use Illuminate\Support\Carbon;
use Kalaanba\Modules\PlayerAffiliation\Domain\Player;
use Kalaanba\Modules\PlayerAffiliation\Domain\PlayerAvailability;
use Kalaanba\Modules\PlayerAffiliation\Domain\PlayerClaimStatus;
use Kalaanba\Modules\PlayerAffiliation\Domain\PlayerMarketStatus;
use Kalaanba\Modules\PlayerAffiliation\Domain\PlayerRepository;

final class EloquentPlayerRepository implements PlayerRepository
{
    public function findByUserId(string $userId): ?Player
    {
        /** @var PlayerRecord|null $row */
        $row = PlayerRecord::query()
            ->where('user_id', $userId)
            ->whereNull('archived_at')
            ->first();

        return $row === null ? null : $this->map($row);
    }

    public function findById(string $id): ?Player
    {
        /** @var PlayerRecord|null $row */
        $row = PlayerRecord::query()->whereNull('archived_at')->find($id);

        return $row === null ? null : $this->map($row);
    }

    public function create(Player $player): Player
    {
        $record = new PlayerRecord;
        $record->forceFill([
            'id' => $player->id,
            'user_id' => $player->userId,
            'first_name' => $player->firstName,
            'last_name' => $player->lastName,
            'stage_name' => $player->stageName,
            'preferred_number' => $player->preferredNumber,
            'primary_position' => $player->primaryPosition,
            'availability_status' => $player->availability->value,
            'market_status' => $player->marketStatus->value,
            'claim_status' => $player->claimStatus->value,
            'headshot_url' => $player->headshotUrl,
            'created_at' => Carbon::instance($player->createdAt),
            'updated_at' => Carbon::instance($player->createdAt),
        ])->save();

        return $this->map($record->refresh());
    }

    private function map(PlayerRecord $row): Player
    {
        $createdAt = $row->getAttribute('created_at');
        $preferred = $row->getAttribute('preferred_number');

        return new Player(
            id: (string) $row->getAttribute('id'),
            userId: (string) $row->getAttribute('user_id'),
            firstName: (string) $row->getAttribute('first_name'),
            lastName: (string) $row->getAttribute('last_name'),
            stageName: (string) $row->getAttribute('stage_name'),
            preferredNumber: $preferred !== null ? (int) $preferred : null,
            primaryPosition: $row->getAttribute('primary_position') !== null
                ? (string) $row->getAttribute('primary_position')
                : null,
            availability: PlayerAvailability::from((string) $row->getAttribute('availability_status')),
            marketStatus: PlayerMarketStatus::from((string) $row->getAttribute('market_status')),
            claimStatus: PlayerClaimStatus::from((string) $row->getAttribute('claim_status')),
            headshotUrl: $row->getAttribute('headshot_url') !== null
                ? (string) $row->getAttribute('headshot_url')
                : null,
            createdAt: $createdAt instanceof Carbon
                ? $createdAt->toDateTimeImmutable()
                : new DateTimeImmutable((string) $createdAt),
        );
    }
}
