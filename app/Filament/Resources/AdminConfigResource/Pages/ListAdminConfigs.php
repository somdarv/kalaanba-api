<?php

declare(strict_types=1);

namespace App\Filament\Resources\AdminConfigResource\Pages;

use App\Filament\Resources\AdminConfigResource;
use Filament\Resources\Pages\ListRecords;

class ListAdminConfigs extends ListRecords
{
    protected static string $resource = AdminConfigResource::class;
}
