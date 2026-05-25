<?php

declare(strict_types=1);

namespace App\Filament\Resources\PersonalAccessTokenResource\Pages;

use App\Filament\Resources\PersonalAccessTokenResource;
use Filament\Resources\Pages\ListRecords;

class ListPersonalAccessTokens extends ListRecords
{
    protected static string $resource = PersonalAccessTokenResource::class;
}
