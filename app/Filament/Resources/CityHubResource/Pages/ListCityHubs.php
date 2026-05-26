<?php

declare(strict_types=1);

namespace App\Filament\Resources\CityHubResource\Pages;

use App\Filament\Resources\CityHubResource;
use Filament\Resources\Pages\ListRecords;

class ListCityHubs extends ListRecords
{
    protected static string $resource = CityHubResource::class;
}
