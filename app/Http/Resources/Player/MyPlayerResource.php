<?php

declare(strict_types=1);

namespace App\Http\Resources\Player;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Kalaanba\Modules\PlayerAffiliation\Domain\CardConfidence;
use Kalaanba\Modules\PlayerAffiliation\Domain\Player;
use Kalaanba\Modules\PlayerAffiliation\Domain\VerifiedRecord;

/**
 * The owner's view of their own player record.
 *
 * Contract: contracts/api/player/get-players-me.v1.yaml, and the 200 body of
 * patch-players-id.v1.yaml — both endpoints return this shape so a client can
 * replace its cache entry outright instead of merging a partial into it.
 *
 * Wraps the DOMAIN entity, never an Eloquent model (engineering standards §4).
 *
 * Every string here is a stable internal key. No labels: those are resolved
 * from Admin Configuration through `GET /players/meta` (Law 4, ADR-0007), so
 * an admin renaming "Available" never has to touch this file.
 *
 * `archived_at` is absent rather than null. Both reads that feed this resource
 * exclude archived players, so the field could only ever carry null and a
 * always-null key invites a client to write a check that never fires.
 *
 * @property-read Player $resource
 */
final class MyPlayerResource extends JsonResource
{
    public function __construct(
        Player $player,
        private readonly CardConfidence $confidence,
        private readonly VerifiedRecord $record,
    ) {
        parent::__construct($player);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource->id,
            'user_id' => $this->resource->userId,
            'first_name' => $this->resource->firstName,
            'last_name' => $this->resource->lastName,
            'stage_name' => $this->resource->stageName,
            'preferred_number' => $this->resource->preferredNumber,
            'primary_position' => $this->resource->primaryPosition,
            'availability_status' => $this->resource->availability->value,
            'market_status' => $this->resource->marketStatus->value,
            'claim_status' => $this->resource->claimStatus->value,
            'headshot_url' => $this->resource->headshotUrl,
            'confidence' => [
                'tier' => $this->confidence->tier,
                'confirmed_matches' => $this->confidence->confirmedMatches,
                'next_tier' => $this->confidence->nextTier,
                'matches_to_next_tier' => $this->confidence->matchesToNextTier,
            ],
            // Always present, never omitted, even when every counter is zero.
            // §13: a client that has to tell "no stats yet" apart from "field
            // missing" will guess, and a guessed stat is a claimed stat.
            'record' => [
                'appearances' => $this->record->appearances,
                'goals' => $this->record->goals,
                'assists' => $this->record->assists,
                'minutes' => $this->record->minutes,
                'yellow_cards' => $this->record->yellowCards,
                'red_cards' => $this->record->redCards,
            ],
        ];
    }
}
