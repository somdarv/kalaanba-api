<?php

declare(strict_types=1);

namespace App\Filament\Resources\UserResource\Pages;

use App\Filament\Resources\UserResource;
use Filament\Actions\Action;
use Filament\Resources\Pages\EditRecord;

/**
 * Edit-user page. DeleteAction intentionally NOT registered (Constitution L13).
 */
class EditUser extends EditRecord
{
    protected static string $resource = UserResource::class;

    /**
     * @return array<int, Action>
     */
    protected function getHeaderActions(): array
    {
        return [];
    }
}
