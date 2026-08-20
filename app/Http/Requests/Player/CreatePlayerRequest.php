<?php

declare(strict_types=1);

namespace App\Http\Requests\Player;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Kalaanba\Modules\PlayerAffiliation\Application\CreatePlayerProfile;
use Kalaanba\Modules\PlayerAffiliation\Application\PlayerProfileVocabulary;

/**
 * POST /api/v1/players — create the caller's player profile.
 *
 * Shape + bounds validation only; one-per-user idempotency lives in
 * {@see CreatePlayerProfile}.
 * Every bound is config-driven (Constitution §1.2) and every accepted set is a
 * stable internal key (§1.4). Player & Affiliation engine doc §3, §6, §12.
 *
 * Bounds and option sets are read through {@see PlayerProfileVocabulary}, the
 * same resolver that feeds `GET /api/v1/players/meta`. Sharing it is the point:
 * when the two read config separately, a set can be accepted here and never
 * offered by the form — or the reverse.
 */
final class CreatePlayerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, array<int, mixed>|string>
     */
    public function rules(): array
    {
        $vocabulary = $this->vocabulary();
        $bounds = $vocabulary->bounds();

        $nameMax = $bounds['name_max_length'];
        $stageMax = $bounds['stage_name_max_length'];
        $numberMin = $bounds['preferred_number_min'];
        $numberMax = $bounds['preferred_number_max'];

        return [
            'first_name' => ['required', 'string', 'min:1', "max:{$nameMax}"],
            'last_name' => ['required', 'string', 'min:1', "max:{$nameMax}"],
            'stage_name' => ['required', 'string', 'min:1', "max:{$stageMax}"],
            'preferred_number' => ['nullable', 'integer', "min:{$numberMin}", "max:{$numberMax}"],
            'primary_position' => ['nullable', 'string', Rule::in($vocabulary->positionKeys())],
            'availability_status' => ['nullable', 'string', Rule::in($vocabulary->availabilityKeys())],
            'headshot_url' => ['nullable', 'url', 'max:2048'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'first_name.*' => 'player.profile.name_invalid',
            'last_name.*' => 'player.profile.name_invalid',
            'stage_name.*' => 'player.profile.stage_name_invalid',
            'preferred_number.*' => 'player.profile.preferred_number_out_of_range',
            'primary_position.*' => 'player.profile.position_unknown',
            'availability_status.*' => 'player.profile.availability_unknown',
        ];
    }

    private function vocabulary(): PlayerProfileVocabulary
    {
        return app(PlayerProfileVocabulary::class);
    }
}
