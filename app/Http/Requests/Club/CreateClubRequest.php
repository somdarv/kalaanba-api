<?php

declare(strict_types=1);

namespace App\Http\Requests\Club;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;
use Kalaanba\Modules\Club\Application\ClubVocabulary;
use Kalaanba\Modules\Club\Application\CreateClub;
use Kalaanba\Modules\Club\Domain\ClubTier;

/**
 * POST /api/v1/clubs — create a club (creator becomes Owner).
 *
 * Shape validation only. City Hub / Area existence and the reserved-name policy
 * are domain rules and live in {@see CreateClub}. The option sets and the name
 * bounds come from {@see ClubVocabulary}, the same source the meta endpoint
 * serves, so a type the form offers can never be one the validator refuses.
 *
 * Club engine doc §3, §5; ADR-0007 for the vocabulary, ADR-0017 for the tier.
 */
final class CreateClubRequest extends FormRequest
{
    /**
     * Resolved by the container, not fetched from it: a Form Request is an
     * HTTP-layer object and may take a dependency, but reaching for `app()`
     * inside a method hides that it has one.
     */
    public function __construct(
        private readonly ClubVocabulary $vocabulary,
    ) {
        parent::__construct();
    }

    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, array<int, mixed>|string>
     */
    public function rules(): array
    {
        $bounds = $this->vocabulary->nameBounds();

        return [
            'name' => [
                'required',
                'string',
                'min:'.$bounds['min'],
                'max:'.$bounds['max'],
            ],
            'tier' => ['required', 'string', Rule::in($this->vocabulary->tierKeys())],
            'club_type' => ['required', 'string', Rule::in($this->vocabulary->typeKeys())],
            'city_hub_id' => ['required', 'uuid'],
            'area_id' => ['required', 'uuid'],
            'crest_url' => ['nullable', 'url', 'max:2048'],
        ];
    }

    /**
     * The type must belong to the tier the person picked.
     *
     * Checked here rather than in `rules()` because it is a relationship
     * between two fields, and it runs only once both have passed their own
     * rules — otherwise an unknown tier would produce two errors describing the
     * same mistake.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $tier = ClubTier::tryFrom((string) $this->input('tier'));
            if ($tier === null) {
                return;
            }

            $allowed = $this->vocabulary->typeKeysForTier($tier);

            if (! in_array((string) $this->input('club_type'), $allowed, true)) {
                $validator->errors()->add('club_type', 'club.type_wrong_tier');
            }
        });
    }

    /**
     * Stable error keys, never display strings (Constitution Law 4). The client
     * owns the copy and maps each key back to the step that produced it.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.*' => 'club.name_invalid',
            'tier.*' => 'club.tier_unknown',
            'club_type.*' => 'club.type_unknown',
            'city_hub_id.*' => 'club.city_hub_invalid',
            'area_id.*' => 'club.area_invalid',
        ];
    }

    /** The tier as a domain value, once validation has passed. */
    public function tier(): ClubTier
    {
        return ClubTier::tryFrom((string) $this->validated('tier')) ?? ClubTier::Amateur;
    }
}
