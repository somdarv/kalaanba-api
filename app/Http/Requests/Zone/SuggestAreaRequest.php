<?php

declare(strict_types=1);

namespace App\Http\Requests\Zone;

use Illuminate\Foundation\Http\FormRequest;
use Kalaanba\Modules\Zone\Application\SubmitAreaSuggestion;
use Kalaanba\Support\Config as KxConfig;

/**
 * POST /api/v1/zone/area-suggestions (user-facing).
 *
 * Shape + length validation only; City Hub existence is verified in
 * {@see SubmitAreaSuggestion}. Name bounds are
 * config-driven (zone.area_suggestion_min/max_name_length) — no magic numbers
 * (Constitution §1.2).
 */
final class SuggestAreaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, array<int, string>|string>
     */
    public function rules(): array
    {
        $min = $this->configInt('zone.area_suggestion_min_name_length', 3);
        $max = $this->configInt('zone.area_suggestion_max_name_length', 80);

        return [
            'city_hub_id' => ['required', 'uuid'],
            'proposed_name' => ['required', 'string', "min:{$min}", "max:{$max}"],
            'note' => ['nullable', 'string', 'max:280'],
        ];
    }

    private function configInt(string $key, int $fallback): int
    {
        try {
            $value = KxConfig::get($key);

            return $value === null ? $fallback : (int) $value->value;
        } catch (\Throwable) {
            return $fallback;
        }
    }
}
