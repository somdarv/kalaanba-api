<?php

declare(strict_types=1);

namespace App\Http\Requests\Club;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Kalaanba\Modules\Club\Application\CreateClub;
use Kalaanba\Support\Config as KxConfig;

/**
 * POST /api/v1/clubs — create a club (creator becomes Owner).
 *
 * Shape validation only; City Hub / Area existence is verified in
 * {@see CreateClub}. `club_type` is checked
 * against the `club.types` config set (Constitution §1.2, §1.4). Club engine
 * doc §3, §5.
 */
final class CreateClubRequest extends FormRequest
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
        return [
            'name' => ['required', 'string', 'min:2', 'max:120'],
            'club_type' => ['required', 'string', Rule::in($this->allowedClubTypes())],
            'city_hub_id' => ['required', 'uuid'],
            'area_id' => ['required', 'uuid'],
            'crest_url' => ['nullable', 'url', 'max:2048'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.*' => 'club.name_invalid',
            'club_type.*' => 'club.type_unknown',
            'city_hub_id.*' => 'club.city_hub_invalid',
            'area_id.*' => 'club.area_invalid',
        ];
    }

    /**
     * @return array<int, string>
     */
    private function allowedClubTypes(): array
    {
        $fallback = [
            'community', 'informal', 'school', 'academy', 'corporate',
            'religious', 'institution', 'facility', 'registered',
        ];

        try {
            $value = KxConfig::get('club.types');
            if ($value === null) {
                return $fallback;
            }
            $decoded = json_decode((string) $value->value, true);

            return is_array($decoded) && $decoded !== []
                ? array_values(array_filter($decoded, 'is_string'))
                : $fallback;
        } catch (\Throwable) {
            return $fallback;
        }
    }
}
