<?php

declare(strict_types=1);

namespace App\Http\Requests\Player;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Kalaanba\Modules\PlayerAffiliation\Application\PlayerProfileVocabulary;
use Kalaanba\Modules\PlayerAffiliation\Application\UpdatePlayerProfile;

/**
 * PATCH /api/v1/players/{playerId} — edit your own profile.
 *
 * Shape + bounds only; ownership lives in {@see UpdatePlayerProfile} so every
 * future caller inherits it (engine doc §17).
 *
 * Bounds and option sets come from {@see PlayerProfileVocabulary}, the same
 * resolver behind `GET /players/meta` and `CreatePlayerRequest`. Three callers,
 * one read: a value cannot be accepted here and never offered by the form.
 *
 * **`sometimes` on every rule is what makes this a PATCH.** Without it an
 * absent key validates as null and clears a field the caller never mentioned,
 * which would turn a one-field availability tap into a profile wipe.
 *
 * `market_status`, `claim_status`, `confidence` and `record` are absent from
 * the rules AND rejected outright below. All four are backend-derived (Law 3),
 * and silently dropping a field a client believed it set is how truth drifts.
 */
final class UpdatePlayerRequest extends FormRequest
{
    /**
     * Everything a caller may move. The complement of this list is refused
     * rather than ignored.
     */
    private const EDITABLE = [
        'first_name',
        'last_name',
        'stage_name',
        'preferred_number',
        'primary_position',
        'availability_status',
        'headshot_url',
    ];

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
            'first_name' => ['sometimes', 'required', 'string', 'min:1', "max:{$nameMax}"],
            'last_name' => ['sometimes', 'required', 'string', 'min:1', "max:{$nameMax}"],
            'stage_name' => ['sometimes', 'required', 'string', 'min:1', "max:{$stageMax}"],
            'preferred_number' => ['sometimes', 'nullable', 'integer', "min:{$numberMin}", "max:{$numberMax}"],
            'primary_position' => ['sometimes', 'nullable', 'string', Rule::in($vocabulary->positionKeys())],
            // Not nullable: availability has no "unset" state (§12). Clearing it
            // would drop a player out of every club readiness summary silently.
            'availability_status' => ['sometimes', 'required', 'string', Rule::in($vocabulary->availabilityKeys())],
            'headshot_url' => ['sometimes', 'nullable', 'url', 'max:2048'],
        ];
    }

    public function withValidator(mixed $validator): void
    {
        $validator->after(function (mixed $v): void {
            $keys = array_keys($this->all());

            $unknown = array_diff($keys, self::EDITABLE);
            foreach ($unknown as $key) {
                $v->errors()->add((string) $key, 'player.profile.field_not_editable');
            }

            if (array_intersect($keys, self::EDITABLE) === []) {
                $v->errors()->add('*', 'player.profile.nothing_to_update');
            }
        });
    }

    /**
     * The validated payload narrowed to editable keys.
     *
     * `validated()` already excludes unknown keys, but this is the value the
     * use case receives and "absent means leave alone" only holds if nothing
     * else can slip in.
     *
     * @return array<string, mixed>
     */
    public function changes(): array
    {
        /** @var array<string, mixed> $validated */
        $validated = $this->validated();

        return array_intersect_key($validated, array_flip(self::EDITABLE));
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
