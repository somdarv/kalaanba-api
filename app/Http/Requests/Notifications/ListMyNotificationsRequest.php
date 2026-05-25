<?php

declare(strict_types=1);

namespace App\Http\Requests\Notifications;

use Illuminate\Foundation\Http\FormRequest;
use Kalaanba\Modules\NotificationDistribution\Domain\InboxCategory;
use Kalaanba\Modules\NotificationDistribution\Domain\InboxStatus;

/**
 * Validation for GET /api/v1/me/notifications.
 *
 * Per contracts/api/notification-distribution/get-me-notifications.v1.yaml.
 */
final class ListMyNotificationsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, string>|string>
     */
    public function rules(): array
    {
        return [
            'cursor' => ['nullable', 'string', 'max:512'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:100'],
            'status' => ['nullable', 'string', 'in:'.implode(',', array_column(InboxStatus::cases(), 'value'))],
            'category' => ['nullable', 'string', 'in:'.implode(',', array_column(InboxCategory::cases(), 'value'))],
        ];
    }
}
