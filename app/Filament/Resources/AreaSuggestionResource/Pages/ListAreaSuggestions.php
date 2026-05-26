<?php

declare(strict_types=1);

namespace App\Filament\Resources\AreaSuggestionResource\Pages;

use App\Filament\Resources\AreaSuggestionResource;
use Filament\Resources\Pages\ListRecords;

class ListAreaSuggestions extends ListRecords
{
    protected static string $resource = AreaSuggestionResource::class;
}
