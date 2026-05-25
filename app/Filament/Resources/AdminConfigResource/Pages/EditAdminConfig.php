<?php

declare(strict_types=1);

namespace App\Filament\Resources\AdminConfigResource\Pages;

use App\Filament\Resources\AdminConfigResource;
use App\Models\Admin\AdminConfig;
use Filament\Actions\Action;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

/**
 * EditAdminConfig — saves a NEW row (effective-dated) rather than mutating
 * the existing record. This preserves config history without a separate
 * audit table (L2 + L5).
 */
class EditAdminConfig extends EditRecord
{
    protected static string $resource = AdminConfigResource::class;

    /**
     * @return array<int, Action>
     */
    protected function getHeaderActions(): array
    {
        return [];
    }

    /**
     * @param  Model  $record
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordUpdate($record, array $data): Model
    {
        // Don't mutate — fork a new row with bumped version.
        $existing = AdminConfig::query()
            ->where('key', $data['key'])
            ->where('scope', $data['scope'])
            ->where('scope_id', $data['scope_id'] ?? null)
            ->orderByDesc('version')
            ->first();

        $next = new AdminConfig;
        $next->forceFill([
            ...$data,
            'version' => $existing === null ? 1 : ($existing->version + 1),
        ])->save();

        return $next;
    }
}
